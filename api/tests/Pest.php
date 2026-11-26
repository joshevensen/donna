<?php

use App\Ai\Agents\Donna;
use App\Jobs\EmbedMessage;
use App\Jobs\GenerateReply;
use App\Jobs\TagTopics;
use App\Models\CharacterProfile;
use App\Models\Message;
use App\Models\Person;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Sleep;
use Laravel\Ai\Embeddings;
use Laravel\Ai\Events\ToolFailed;
use Laravel\Ai\Events\ToolInvoked;
use Laravel\Ai\Prompts\EmbeddingsPrompt;
use Laravel\Ai\Responses\Data\ToolCall;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| Feature tests run against Postgres with pgvector, so vector and full-text
| queries are the real thing. Nothing reaches the network and nothing sleeps.
|
*/

pest()->extend(TestCase::class)
    ->use(LazilyRefreshDatabase::class)
    ->beforeEach(function () {
        Http::preventStrayRequests();
        Sleep::fake(syncWithCarbon: true);
    })
    ->in('Feature');

/*
|--------------------------------------------------------------------------
| Helpers
|--------------------------------------------------------------------------
*/

/**
 * A unit vector along one axis of the embedding space.
 *
 * Two texts given the same axis are identical to pgvector; different axes
 * are unrelated. Tests use it to decide what is "about" what.
 *
 * @return list<float>
 */
function embedding(int $axis): array
{
    $vector = array_fill(0, config('donna.models.embedding.dimensions'), 0.0);
    $vector[$axis] = 1.0;

    return $vector;
}

/**
 * The world as it is after the first deploy: Josh, Donna, and who she is.
 *
 * @return array{josh: Person, donna: Person, user: User}
 */
function donnaExists(): array
{
    $user = User::factory()->create();

    CharacterProfile::factory()->create();

    return [
        'user' => $user,
        'josh' => Person::factory()->josh()->create(['user_id' => $user->id]),
        'donna' => Person::factory()->donna()->create(),
    ];
}

/**
 * Fake embeddings so that a text containing one of the given keywords lands
 * on that keyword's axis. Anything else lands on axis 0.
 *
 * @param  array<string, int>  $axes
 */
function fakeEmbeddings(array $axes = []): void
{
    Embeddings::fake(fn (EmbeddingsPrompt $prompt): array => array_map(function (string $input) use ($axes): array {
        foreach ($axes as $keyword => $axis) {
            if (str_contains(mb_strtolower($input), mb_strtolower($keyword))) {
                return embedding($axis);
            }
        }

        return embedding(0);
    }, $prompt->inputs));
}

/**
 * A message of Josh's with Donna's pending reply, as POST /messages leaves them.
 *
 * @return Message The pending reply; the message it answers is its `inReplyTo`.
 */
function pendingReplyTo(string $body): Message
{
    return Message::factory()->pending()->create([
        'in_reply_to_id' => Message::factory()->create(['body' => $body])->id,
    ]);
}

/**
 * Have Donna call her tools while replying to Josh, through the real reply
 * job, and return what each tool told her.
 *
 * @param  list<array{0: string, 1: array<string, mixed>}>  $calls  Tool name and arguments, in order.
 * @return list<string>
 */
function donnaCalls(array $calls, string $said = 'Go ahead.'): array
{
    Queue::fake([EmbedMessage::class, TagTopics::class]);

    $results = [];

    Event::listen(ToolInvoked::class, function (ToolInvoked $event) use (&$results): void {
        $results[] = (string) $event->result;
    });

    Event::listen(ToolFailed::class, function (ToolFailed $event) use (&$results): void {
        $results[] = 'FAILED: '.$event->exception->getMessage();
    });

    Donna::fake([
        ...array_map(fn (array $call, int $index): ToolCall => new ToolCall("call_{$index}", $call[0], $call[1]), $calls, array_keys($calls)),
        'Done.',
    ]);

    GenerateReply::dispatchSync(pendingReplyTo($said));

    return $results;
}
