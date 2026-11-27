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

it('returns 401 without a token or a session', function (string $method, string $uri) {
    $this->json($method, $uri)->assertUnauthorized();
})->with([
    'listing messages' => ['GET', '/api/v1/messages'],
    'sending a message' => ['POST', '/api/v1/messages'],
    'reading a message' => ['GET', '/api/v1/messages/1'],
    'retrying a reply' => ['POST', '/api/v1/messages/1/retry'],
    'flagging a reply' => ['POST', '/api/v1/messages/1/evaluation'],
    'reading the account' => ['GET', '/api/v1/me'],
    'signing out' => ['POST', '/api/v1/auth/logout'],
    'signing out everywhere' => ['POST', '/api/v1/auth/logout-everywhere'],
]);

describe('store', function () {
    it('saves the message and a pending reply before answering, and starts the reply', function (string $source) {
        Queue::fake([GenerateReply::class]);
        actingAs(User::factory()->create());

        $response = postJson('/api/v1/messages', ['body' => 'Park Storyrift for now.', 'source' => $source]);

        $message = Message::where('author', MessageAuthor::Josh)->sole();
        $reply = Message::where('author', MessageAuthor::Donna)->sole();

        $response->assertAccepted()
            ->assertJsonPath('data.message.id', $message->id)
            ->assertJsonPath('data.message.body', 'Park Storyrift for now.')
            ->assertJsonPath('data.message.status', 'complete')
            ->assertJsonPath('data.reply.id', $reply->id)
            ->assertJsonPath('data.reply.status', 'pending')
            ->assertJsonPath('data.reply.in_reply_to_id', $message->id);

        expect($message->source->value)->toBe($source)
            ->and($reply->status)->toBe(MessageStatus::Pending);

        Queue::assertPushedOn('replies', GenerateReply::class, fn (GenerateReply $job): bool => $job->reply->is($reply));
    })->with(['terminal', 'web']);

    it('returns the original pair, and starts nothing new, when a send is repeated with the same Idempotency-Key', function () {
        Queue::fake([GenerateReply::class]);
        actingAs(User::factory()->create());
        $send = fn () => postJson('/api/v1/messages', ['body' => 'Did the order ship?', 'source' => 'web'], [
            'Idempotency-Key' => '0b9b1d5e-6f0a-4c1e-9a53-2f4e8d7c6b5a',
        ]);

        $first = $send()->assertAccepted();
        $again = $send()->assertAccepted();

        expect($again->json('data.message.client_key'))->toBe('0b9b1d5e-6f0a-4c1e-9a53-2f4e8d7c6b5a')
            ->and($again->json('data.message.id'))->toBe($first->json('data.message.id'))
            ->and($again->json('data.reply.id'))->toBe($first->json('data.reply.id'))
            ->and(Message::count())->toBe(2);

        Queue::assertPushedTimes(GenerateReply::class, 1);
    });

    it('stops accepting messages after sixty in a minute, so a stolen session cannot run up the bill', function () {
        Queue::fake([GenerateReply::class]);
        actingAs(User::factory()->create());

        foreach (range(1, 60) as $sent) {
            postJson('/api/v1/messages', ['body' => 'Again.', 'source' => 'web'])->assertAccepted();
        }

        postJson('/api/v1/messages', ['body' => 'Again.', 'source' => 'web'])->assertTooManyRequests();

        expect(Message::where('author', MessageAuthor::Josh)->count())->toBe(60);
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
});

describe('show', function () {
    it('returns a finished reply as its bubbles, with what she looked at and any button she offered', function () {
        actingAs(User::factory()->create());
        $message = Message::factory()->create();
        $reply = Message::factory()->replyTo($message)->create([
            'body' => "It ships Tuesday.\n---\nWant me to remind you?",
            'meta' => [
                'citations' => [['url' => 'https://example.com/ship', 'title' => 'Shipping']],
                'sources' => [['type' => 'fact', 'label' => 'The keyboard was ordered Nov 3 · Nov 3']],
                'actions' => [['type' => 'enable_notifications', 'label' => 'Turn on notifications']],
            ],
        ]);

        getJson("/api/v1/messages/{$reply->id}")
            ->assertOk()
            ->assertExactJson(['data' => [
                'id' => $reply->id,
                'author' => 'donna',
                'body' => "It ships Tuesday.\n---\nWant me to remind you?",
                'bubbles' => ['It ships Tuesday.', 'Want me to remind you?'],
                'source' => 'terminal',
                'status' => 'complete',
                'in_reply_to_id' => $message->id,
                'error' => null,
                'citations' => [['url' => 'https://example.com/ship', 'title' => 'Shipping']],
                'sources' => [
                    ['type' => 'web', 'label' => 'Shipping', 'url' => 'https://example.com/ship'],
                    ['type' => 'fact', 'label' => 'The keyboard was ordered Nov 3 · Nov 3'],
                ],
                'actions' => [['type' => 'enable_notifications', 'label' => 'Turn on notifications']],
                'client_key' => null,
                'created_at' => $reply->created_at->toJSON(),
            ]]);
    });

    it('never splits what Josh typed, and caps what she looked at at eight', function () {
        actingAs(User::factory()->create());
        $message = Message::factory()->create(['body' => "Above\n---\nBelow"]);
        $reply = Message::factory()->replyTo($message)->create(['meta' => [
            'citations' => [['url' => 'https://example.com/a', 'title' => null]],
            'sources' => array_map(fn (int $n): array => ['type' => 'fact', 'label' => "Fact {$n}"], range(1, 8)),
        ]]);

        getJson("/api/v1/messages/{$message->id}")->assertJsonPath('data.bubbles', ["Above\n---\nBelow"]);

        getJson("/api/v1/messages/{$reply->id}")
            ->assertJsonCount(8, 'data.sources')
            ->assertJsonPath('data.sources.0', ['type' => 'web', 'label' => 'https://example.com/a', 'url' => 'https://example.com/a'])
            ->assertJsonPath('data.sources.7.label', 'Fact 7');
    });

    it('returns the error of a reply that failed', function () {
        actingAs(User::factory()->create());
        $reply = Message::factory()->failed()->create();

        getJson("/api/v1/messages/{$reply->id}")
            ->assertOk()
            ->assertJsonPath('data.status', 'failed')
            ->assertJsonPath('data.error', 'Donna could not reply.');
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

    it('returns only what is newer than `after`, oldest first, so an open app can catch up', function () {
        actingAs(User::factory()->create());
        [$first, $second, $third, $fourth] = Message::factory()->count(4)->create()->all();

        getJson("/api/v1/messages?after={$first->id}&limit=2")
            ->assertOk()
            ->assertJsonPath('data.*.id', [$second->id, $third->id])
            ->assertJsonPath('meta.next_after', $third->id);

        getJson("/api/v1/messages?after={$third->id}")
            ->assertOk()
            ->assertJsonPath('data.*.id', [$fourth->id])
            ->assertJsonPath('meta.next_after', null);
    });
});

describe('retry', function () {
    it('sets a failed reply back to pending and starts it again', function () {
        Queue::fake([GenerateReply::class]);
        actingAs(User::factory()->create());
        $reply = Message::factory()->failed()->create(['in_reply_to_id' => Message::factory()->create()->id]);

        postJson("/api/v1/messages/{$reply->id}/retry")
            ->assertAccepted()
            ->assertJsonPath('data.id', $reply->id)
            ->assertJsonPath('data.status', 'pending')
            ->assertJsonPath('data.error', null);

        expect($reply->refresh()->status)->toBe(MessageStatus::Pending);
        Queue::assertPushedOn('replies', GenerateReply::class, fn (GenerateReply $job): bool => $job->reply->is($reply));
    });

    it('refuses anything that is not a failed reply of Donna\'s', function (Closure $message) {
        Queue::fake([GenerateReply::class]);
        actingAs(User::factory()->create());
        $message = $message();
        $status = $message->status;

        postJson("/api/v1/messages/{$message->id}/retry")->assertConflict();

        expect($message->refresh()->status)->toBe($status);
        Queue::assertNotPushed(GenerateReply::class);
    })->with([
        'a finished reply' => [fn () => Message::factory()->fromDonna()->create()],
        'a reply she is still writing' => [fn () => Message::factory()->pending()->create()],
        'a message of Josh\'s' => [fn () => Message::factory()->create()],
    ]);
});

describe('me', function () {
    it('tells the web app who is signed in, the timezone for dates, and whether push is set up', function (array $vapid, bool $configured) {
        config(['donna.timezone' => 'America/Denver', 'donna.push.vapid' => $vapid]);
        actingAs(User::factory()->create(['name' => 'Josh']));

        getJson('/api/v1/me')
            ->assertOk()
            ->assertExactJson(['data' => [
                'name' => 'Josh',
                'timezone' => 'America/Denver',
                'push' => ['configured' => $configured, 'public_key' => $configured ? 'public' : null],
            ]]);
    })->with([
        'with VAPID keys' => [['subject' => null, 'public_key' => 'public', 'private_key' => 'private'], true],
        'with half a key pair' => [['subject' => null, 'public_key' => 'public', 'private_key' => null], false],
        'without them' => [['subject' => null, 'public_key' => null, 'private_key' => null], false],
    ]);
});
