<?php

use App\Ai\Agents\TopicTagger;
use App\Enums\CostPurpose;
use App\Jobs\TagTopics;
use App\Models\Cost;
use App\Models\Message;
use App\Models\Project;
use App\Models\Topic;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Exceptions;
use Illuminate\Support\Facades\Http;
use Laravel\Ai\Embeddings;
use Laravel\Ai\Prompts\AgentPrompt;

use function Pest\Laravel\travelTo;

/**
 * A finished exchange, ready to be tagged.
 *
 * @return array{0: Message, 1: Message}
 */
function exchange(string $said, string $answered, ?string $at = null): array
{
    $message = Message::factory()->create(['body' => $said, 'embedding' => embedding(2), 'created_at' => $at ?? now()]);

    return [$message, Message::factory()->replyTo($message)->create(['body' => $answered, 'created_at' => $at ?? now()])];
}

it('links both messages of the exchange to the existing topics the model picked', function () {
    fakeEmbeddings();
    travelTo('2026-11-26 15:00:00');
    $keyboards = Topic::factory()->create(['name' => 'Mechanical keyboards', 'embedding' => embedding(2), 'first_discussed_at' => '2026-09-01 10:00:00', 'last_discussed_at' => '2026-10-01 10:00:00']);
    $other = Topic::factory()->create(['name' => 'Yarn shows', 'embedding' => embedding(3)]);
    [$message, $reply] = exchange('I found another keyboard.', 'Of course you did.');
    TopicTagger::fake([['existing_topic_ids' => [$keyboards->id], 'new_topics' => []]]);

    TagTopics::dispatchSync($message, $reply);

    expect($message->topics->modelKeys())->toBe([$keyboards->id])
        ->and($reply->topics->modelKeys())->toBe([$keyboards->id])
        ->and($keyboards->refresh())
        ->first_discussed_at->toDateTimeString()->toBe('2026-09-01 10:00:00')
        ->last_discussed_at->toDateTimeString()->toBe('2026-11-26 15:00:00')
        ->and($other->messages()->count())->toBe(0);

    TopicTagger::assertPrompted(fn (AgentPrompt $prompt): bool => $prompt->contains("id {$keyboards->id}: Mechanical keyboards")
        && $prompt->contains('I found another keyboard.'));
});

it('creates and embeds a topic the model names for the first time', function () {
    fakeEmbeddings(['trip' => 6]);
    travelTo('2026-11-26 15:00:00');
    [$message, $reply] = exchange('Let\'s plan the Texas trip.', 'Dates first.');
    TopicTagger::fake([['existing_topic_ids' => [], 'new_topics' => ['Texas trip']]]);

    TagTopics::dispatchSync($message, $reply);

    expect(Topic::sole())
        ->name->toBe('Texas trip')
        ->embedding->toBe(embedding(6))
        ->first_discussed_at->toDateTimeString()->toBe('2026-11-26 15:00:00')
        ->last_discussed_at->toDateTimeString()->toBe('2026-11-26 15:00:00')
        ->and($message->topics()->count())->toBe(1);
});

it('reuses an existing topic when the model offers its name as new', function () {
    fakeEmbeddings();
    $existing = Topic::factory()->create(['name' => 'Texas trip', 'embedding' => embedding(9)]);
    [$message, $reply] = exchange('Back to that vacation.', 'Dates first.');
    TopicTagger::fake([['existing_topic_ids' => [], 'new_topics' => ['texas trip']]]);

    TagTopics::dispatchSync($message, $reply);

    expect(Topic::count())->toBe(1)
        ->and($message->topics->modelKeys())->toBe([$existing->id]);
});

it('files a topic under the project it is named after', function () {
    fakeEmbeddings();
    $project = Project::factory()->create(['name' => 'Fibermade', 'aliases' => ['FM']]);
    [$message, $reply] = exchange('FM signups doubled.', 'Good.');
    TopicTagger::fake([['existing_topic_ids' => [], 'new_topics' => ['fm', 'Signups']]]);

    TagTopics::dispatchSync($message, $reply);

    expect($project->topics->pluck('name')->all())->toBe(['fm']);
});

it('ignores a topic id the model was never offered', function () {
    fakeEmbeddings();
    [$message, $reply] = exchange('Morning.', 'Morning.');
    TopicTagger::fake([['existing_topic_ids' => [4242], 'new_topics' => []]]);

    TagTopics::dispatchSync($message, $reply);

    expect($message->topics()->count())->toBe(0);
});

it('records the tagging call as a cost of the exchange', function () {
    fakeEmbeddings();
    [$message, $reply] = exchange('Morning.', 'Morning.');
    TopicTagger::fake([['existing_topic_ids' => [], 'new_topics' => []]]);

    TagTopics::dispatchSync($message, $reply);

    expect(Cost::sole())
        ->purpose->toBe(CostPurpose::Tagging)
        ->model->toBe('claude-haiku-5-5')
        ->trigger_type->toBe('message')
        ->trigger_id->toBe($message->id);
});

it('asks Anthropic for structured output at low effort and applies the JSON it returns', function () {
    fakeEmbeddings();
    $topic = Topic::factory()->create(['name' => 'Mechanical keyboards', 'embedding' => embedding(2)]);
    [$message, $reply] = exchange('I found another keyboard.', 'Of course you did.');

    Http::fake(['api.anthropic.com/v1/messages' => Http::response([
        'id' => 'msg_1',
        'type' => 'message',
        'role' => 'assistant',
        'model' => 'claude-haiku-5-5',
        'content' => [['type' => 'text', 'text' => json_encode(['existing_topic_ids' => [$topic->id], 'new_topics' => []])]],
        'stop_reason' => 'end_turn',
        'usage' => ['input_tokens' => 400, 'output_tokens' => 20],
    ])]);

    TagTopics::dispatchSync($message, $reply);

    Http::assertSent(fn (Request $request): bool => $request['model'] === 'claude-haiku-5-5'
        && $request['output_config']['effort'] === 'low'
        && $request['output_config']['format']['type'] === 'json_schema'
        && ! isset($request['tools']));

    expect($message->topics->modelKeys())->toBe([$topic->id])
        ->and(Cost::sole()->usd)->toBe('0.000050');
});

it('still saves and links new topics when embeddings are down, leaving them for EmbedMissing', function () {
    Exceptions::fake();
    Embeddings::fake(fn () => throw new RuntimeException('Voyage is down.'));
    [$message, $reply] = exchange('I found another keyboard.', 'Of course you did.');
    TopicTagger::fake([['existing_topic_ids' => [], 'new_topics' => ['Mechanical keyboards']]]);

    TagTopics::dispatchSync($message, $reply);

    $topic = Topic::sole();

    expect($topic->name)->toBe('Mechanical keyboards')
        ->and($topic->embedding)->toBeNull()
        ->and($message->topics->modelKeys())->toBe([$topic->id]);
});
