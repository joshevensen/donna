<?php

use App\Enums\MessageAuthor;
use App\Enums\MessageStatus;
use App\Jobs\GenerateReply;
use App\Models\Message;
use App\Models\User;
use Illuminate\Support\Facades\Queue;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\getJson;
use function Pest\Laravel\postJson;

it('returns 401 without a token', function (string $method, string $uri) {
    $this->json($method, $uri)->assertUnauthorized();
})->with([
    'listing messages' => ['GET', '/api/v1/messages'],
    'sending a message' => ['POST', '/api/v1/messages'],
    'reading a message' => ['GET', '/api/v1/messages/1'],
    'flagging a reply' => ['POST', '/api/v1/messages/1/evaluation'],
]);

describe('store', function () {
    it('saves the message and a pending reply before answering, and starts the reply', function () {
        Queue::fake([GenerateReply::class]);
        actingAs(User::factory()->create());

        $response = postJson('/api/v1/messages', ['body' => 'Park Storyrift for now.', 'source' => 'terminal']);

        $message = Message::where('author', MessageAuthor::Josh)->sole();
        $reply = Message::where('author', MessageAuthor::Donna)->sole();

        $response->assertAccepted()
            ->assertJsonPath('data.message.id', $message->id)
            ->assertJsonPath('data.message.body', 'Park Storyrift for now.')
            ->assertJsonPath('data.message.status', 'complete')
            ->assertJsonPath('data.reply.id', $reply->id)
            ->assertJsonPath('data.reply.status', 'pending')
            ->assertJsonPath('data.reply.in_reply_to_id', $message->id);

        expect($message->source->value)->toBe('terminal')
            ->and($reply->status)->toBe(MessageStatus::Pending);

        Queue::assertPushedOn('replies', GenerateReply::class, fn (GenerateReply $job): bool => $job->reply->is($reply));
    });

    it('returns 422 and saves nothing when the body or source is missing', function () {
        Queue::fake([GenerateReply::class]);
        actingAs(User::factory()->create());

        postJson('/api/v1/messages', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'body' => 'The body field is required.',
                'source' => 'The source field is required.',
            ]);

        expect(Message::count())->toBe(0);
        Queue::assertNotPushed(GenerateReply::class);
    });

    it('returns 422 for a source that is not a known input surface', function () {
        actingAs(User::factory()->create());

        postJson('/api/v1/messages', ['body' => 'Hi', 'source' => 'carrier-pigeon'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['source' => 'The selected source is invalid.']);
    });
});

describe('show', function () {
    it('returns a finished reply with its citations so the client can stop polling', function () {
        actingAs(User::factory()->create());
        $message = Message::factory()->create();
        $reply = Message::factory()->replyTo($message)->create([
            'body' => 'It ships Tuesday.',
            'meta' => ['citations' => [['url' => 'https://example.com/ship', 'title' => 'Shipping']]],
        ]);

        getJson("/api/v1/messages/{$reply->id}")
            ->assertOk()
            ->assertExactJson(['data' => [
                'id' => $reply->id,
                'author' => 'donna',
                'body' => 'It ships Tuesday.',
                'source' => 'terminal',
                'status' => 'complete',
                'in_reply_to_id' => $message->id,
                'error' => null,
                'citations' => [['url' => 'https://example.com/ship', 'title' => 'Shipping']],
                'created_at' => $reply->created_at->toJSON(),
            ]]);
    });

    it('returns the error of a reply that failed', function () {
        actingAs(User::factory()->create());
        $reply = Message::factory()->failed()->create();

        getJson("/api/v1/messages/{$reply->id}")
            ->assertOk()
            ->assertJsonPath('data.status', 'failed')
            ->assertJsonPath('data.error', 'Donna could not reply.');
    });

    it('returns 404 for a message that does not exist', function () {
        actingAs(User::factory()->create());

        getJson('/api/v1/messages/999')->assertNotFound();
    });
});

describe('index', function () {
    it('lists the conversation newest first, including a reply that finished while the client was away', function () {
        actingAs(User::factory()->create());
        $message = Message::factory()->create();
        $reply = Message::factory()->replyTo($message)->create();

        getJson('/api/v1/messages')
            ->assertOk()
            ->assertJsonPath('data.*.id', [$reply->id, $message->id])
            ->assertJsonPath('data.0.status', 'complete')
            ->assertJsonPath('meta.next_before', null);
    });

    it('pages back through history with before and limit', function () {
        actingAs(User::factory()->create());
        [$first, $second, $third, $fourth] = Message::factory()->count(4)->create()->all();

        getJson('/api/v1/messages?limit=2')
            ->assertOk()
            ->assertJsonPath('data.*.id', [$fourth->id, $third->id])
            ->assertJsonPath('meta.next_before', $third->id);

        getJson("/api/v1/messages?limit=2&before={$third->id}")
            ->assertOk()
            ->assertJsonPath('data.*.id', [$second->id, $first->id])
            ->assertJsonPath('meta.next_before', null);
    });

    it('returns 422 for a limit above the maximum', function () {
        actingAs(User::factory()->create());

        getJson('/api/v1/messages?limit=500')
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['limit' => 'The limit field must not be greater than 100.']);
    });
});
