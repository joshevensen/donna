<?php

namespace App\Console\Commands;

use App\Console\SubmitKey;
use Carbon\CarbonImmutable;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Sleep;
use Laravel\Prompts\TextareaPrompt;
use Symfony\Component\Console\Formatter\OutputFormatter;

use function Laravel\Prompts\spin;

/**
 * Josh's terminal. It only ever talks to the deployed API over HTTP, so
 * closing it loses nothing: messages and replies live on the server.
 */
#[Signature('donna:chat')]
#[Description('Talk to Donna from the terminal, against the deployed API.')]
class DonnaChat extends Command
{
    /**
     * The newest finished reply of Donna's on screen: what /good and /bad flag.
     */
    private ?int $lastReplyId = null;

    public function handle(): int
    {
        if (blank(config('donna.client.url')) || blank(config('donna.client.token'))) {
            $this->components->error('Set DONNA_API_URL and DONNA_API_TOKEN in .env first. Create the token on the server with `php artisan donna:token`.');

            return self::FAILURE;
        }

        try {
            $this->showHistory(config('donna.client.history'));

            while (true) {
                $input = $this->nextMessage();

                if (str_starts_with($input, '/')) {
                    if ($this->slashCommand(...$this->parseCommand($input)) === false) {
                        break;
                    }

                    continue;
                }

                $this->send($input);
            }
        } catch (ConnectionException) {
            $this->components->error('Could not reach Donna at '.config('donna.client.url').'. Anything already sent is safe on the server.');

            return self::FAILURE;
        } catch (RequestException $exception) {
            $this->components->error($exception->response->unauthorized()
                ? 'Donna did not accept the token. Check DONNA_API_TOKEN.'
                : "Donna's API answered with an error ({$exception->response->status()}). Anything already sent is safe on the server.");

            return self::FAILURE;
        }

        return self::SUCCESS;
    }

    /**
     * Ask for Josh's next message. Enter adds a line; Ctrl+D or the
     * configured function key sends it.
     */
    private function nextMessage(): string
    {
        $submitKey = SubmitKey::configured();

        $prompt = new TextareaPrompt(
            label: 'You',
            placeholder: 'Message Donna, or /good, /bad, /history, /quit',
            required: true,
            hint: $submitKey->hint(),
        );

        $submitKey->attachTo($prompt);

        return trim($prompt->prompt());
    }

    /**
     * Print the last messages, oldest first, and wait for a reply Donna is
     * still writing.
     */
    private function showHistory(int $limit): void
    {
        $messages = array_reverse($this->api()->get('messages', ['limit' => $limit])->json('data'));

        foreach ($messages as $message) {
            if ($message['status'] !== 'pending') {
                $this->show($message);
            }
        }

        $newest = end($messages);

        if ($newest !== false && $newest['status'] === 'pending') {
            $this->show($this->waitFor($newest['id']));
        }
    }

    private function send(string $body): void
    {
        $reply = $this->api()
            ->post('messages', ['body' => $body, 'source' => 'terminal'])
            ->json('data.reply');

        $this->show($this->waitFor($reply['id']));
    }

    /**
     * Poll a pending reply until Donna finishes it, it fails, or it has
     * taken so long that it is better to hand the terminal back.
     *
     * @return array<string, mixed>
     */
    private function waitFor(int $id): array
    {
        return spin(function () use ($id): array {
            $deadline = now()->addSeconds(config('donna.client.wait_seconds'));

            do {
                $message = $this->api()->get("messages/{$id}")->json('data');

                if ($message['status'] !== 'pending') {
                    return $message;
                }

                Sleep::for(config('donna.client.poll_seconds'))->seconds();
            } while (now()->lessThan($deadline));

            return $message;
        }, 'Donna is thinking…');
    }

    /**
     * Split "/good too long" into the command and its argument.
     *
     * @return array{0: string, 1: string}
     */
    private function parseCommand(string $input): array
    {
        [$command, $argument] = array_pad(preg_split('/\s+/', ltrim($input, '/'), 2), 2, '');

        return [mb_strtolower($command), trim($argument)];
    }

    /**
     * Handle a slash command. These never reach Donna.
     *
     * @return bool False when the session should end.
     */
    private function slashCommand(string $command, string $argument): bool
    {
        match ($command) {
            'quit' => null,
            'good', 'bad' => $this->flag($command, $argument),
            'history' => $this->showHistory(max(1, min(100, (int) $argument ?: config('donna.client.history')))),
            default => $this->components->warn("Unknown command /{$command}. Try /good [note], /bad [note], /history [n], or /quit."),
        };

        return $command !== 'quit';
    }

    private function flag(string $rating, string $note): void
    {
        if ($this->lastReplyId === null) {
            $this->components->warn('There is no reply from Donna to flag yet.');

            return;
        }

        $this->api()->post("messages/{$this->lastReplyId}/evaluation", [
            'rating' => $rating,
            'note' => $note === '' ? null : $note,
        ]);

        $this->components->info("Flagged Donna's last reply as a {$rating} example.");
    }

    /**
     * Print one message as plain text.
     *
     * @param  array<string, mixed>  $message
     */
    private function show(array $message): void
    {
        $at = CarbonImmutable::parse($message['created_at'])->timezone(config('donna.timezone'))->format('M j, g:i A');
        $name = $message['author'] === 'donna' ? '<fg=red;options=bold>Donna</>' : '<options=bold>You</>';

        $this->newLine();
        $this->line("{$name} <fg=gray>{$at}</>");

        if ($message['status'] === 'pending') {
            $this->line('<fg=gray>Still working on this one. It will be here when you come back.</>');

            return;
        }

        if ($message['status'] === 'failed') {
            $this->line('<fg=yellow>'.OutputFormatter::escape($message['error']).'</>');

            return;
        }

        // Her words are printed as written: "<info>" in a reply is text, not styling.
        $this->line(OutputFormatter::escape($message['body']));

        foreach ($message['citations'] as $index => $citation) {
            $this->line('<fg=gray>  ['.($index + 1).'] '.OutputFormatter::escape(($citation['title'] ?? $citation['url']).' — '.$citation['url']).'</>');
        }

        if ($message['author'] === 'donna') {
            $this->lastReplyId = $message['id'];
        }
    }

    private function api(): PendingRequest
    {
        return Http::baseUrl(rtrim(config('donna.client.url'), '/').'/api/v1')
            ->withToken(config('donna.client.token'))
            ->acceptJson()
            ->connectTimeout(10)
            ->timeout(30)
            ->throw();
    }
}
