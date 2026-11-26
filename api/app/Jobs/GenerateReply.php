<?php

namespace App\Jobs;

use App\Ai\Agents\Donna;
use App\Enums\MessageStatus;
use App\Memory\ContextBuilder;
use App\Memory\Embedder;
use App\Models\Message;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Support\Facades\Log;
use Laravel\Ai\Responses\AgentResponse;
use Laravel\Ai\Responses\Data\Citation;
use Laravel\Ai\Responses\Data\FinishReason;
use Laravel\Ai\Responses\Data\Step;
use Laravel\Ai\Responses\Data\UrlCitation;
use RuntimeException;
use Throwable;

/**
 * Writes Donna's reply on the server, so it finishes whether or not the
 * terminal that asked is still open.
 */
class GenerateReply implements ShouldQueue
{
    use Queueable;

    /**
     * Releases by the overlap lock count as attempts, so the real limit on
     * retries is the number of exceptions.
     */
    public int $tries = 30;

    public int $maxExceptions = 3;

    /**
     * @var list<int>
     */
    public array $backoff = [5, 30];

    public int $timeout;

    /**
     * A reply that runs out of time is not retried: its tool calls may already have happened.
     */
    public bool $failOnTimeout = true;

    /**
     * What Donna says when the model declines. Her voice, never a raw error.
     */
    public const string DECLINED = "I can't help with that one.";

    /**
     * @param  Message  $reply  Donna's pending reply; the message it answers is its `inReplyTo`.
     */
    public function __construct(public Message $reply)
    {
        $this->timeout = config('donna.models.reply.job_timeout');

        $this->onQueue('replies');
    }

    /**
     * Replies are written one at a time: each needs the one before it in its context.
     *
     * @return list<object>
     */
    public function middleware(): array
    {
        return [
            (new WithoutOverlapping('reply'))->releaseAfter(5)->expireAfter($this->timeout + 30),
        ];
    }

    public function handle(Embedder $embedder, ContextBuilder $contexts): void
    {
        if (! $this->reply->isPending()) {
            return;
        }

        $message = $this->reply->inReplyTo;

        // If embeddings are down Donna still answers, from full-text memory alone.
        if ($message->embedding === null) {
            $message->update(['embedding' => $embedder->attempt($message->body, $message)]);
        }

        $context = $contexts->build($message);

        $response = Donna::make(context: $context, message: $message)->prompt($context->prompt);

        $declined = $response->steps->last()?->finishReason === FinishReason::ContentFilter;

        if ($declined) {
            Log::warning('The model declined to reply.', ['message_id' => $message->id]);
        } elseif (blank($response->text)) {
            throw new RuntimeException('The model returned an empty reply.');
        }

        $this->reply->update([
            'body' => $declined ? self::DECLINED : trim($response->text),
            'status' => MessageStatus::Complete,
            'error' => null,
            'meta' => array_filter([
                'model' => $response->meta->model,
                'declined' => $declined,
                'citations' => $this->citations($response),
            ]),
        ]);

        EmbedMessage::dispatch($this->reply);
        TagTopics::dispatch($message, $this->reply);
    }

    /**
     * Out of retries: say so on the reply itself. Josh's message is safe and
     * the terminal shows the failure.
     */
    public function failed(?Throwable $exception): void
    {
        if (! $this->reply->isPending()) {
            return;
        }

        $this->reply->update([
            'status' => MessageStatus::Failed,
            'error' => 'Donna could not reply. Your message is saved; send it again in a moment.',
        ]);
    }

    /**
     * The web sources cited anywhere in the run, in order, once each.
     *
     * @return list<array{url: string, title: string|null}>
     */
    private function citations(AgentResponse $response): array
    {
        return $response->steps
            ->flatMap(fn (Step $step) => $step->meta->citations)
            ->filter(fn (Citation $citation): bool => $citation instanceof UrlCitation)
            ->unique('url')
            ->map(fn (UrlCitation $citation): array => ['url' => $citation->url, 'title' => $citation->title])
            ->values()
            ->all();
    }
}
