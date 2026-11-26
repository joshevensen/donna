<?php

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Sleep;

use function Pest\Laravel\artisan;

/**
 * A message as the API returns it.
 *
 * @param  array<string, mixed>  $attributes
 * @return array<string, mixed>
 */
function apiMessage(int $id, string $author, string $body, array $attributes = []): array
{
    return $attributes + [
        'id' => $id,
        'author' => $author,
        'body' => $body,
        'source' => 'terminal',
        'status' => 'complete',
        'in_reply_to_id' => null,
        'error' => null,
        'citations' => [],
        'created_at' => '2026-11-26T15:00:00.000000Z',
    ];
}

beforeEach(fn () => config([
    'donna.client.url' => 'https://donna.test',
    'donna.client.token' => 'secret-token',
]));

it('opens on the last few exchanges, oldest first, including a reply that finished while Josh was away', function () {
    Http::fake(['donna.test/api/v1/messages?limit=10' => Http::response(['data' => [
        apiMessage(2, 'donna', 'It shipped while you were out.'),
        apiMessage(1, 'josh', 'Did the order ship?'),
    ]])]);

    artisan('donna:chat')
        ->expectsOutputToContain('Did the order ship?')
        ->expectsOutputToContain('It shipped while you were out.')
        ->expectsQuestion('You', '/quit')
        ->assertSuccessful();

    Http::assertSent(fn (Request $request): bool => $request->hasHeader('Authorization', 'Bearer secret-token'));
});

it('waits on start for a reply Donna is still writing', function () {
    Http::fake([
        'donna.test/api/v1/messages?limit=10' => Http::response(['data' => [
            apiMessage(2, 'donna', '', ['status' => 'pending']),
            apiMessage(1, 'josh', 'Did the order ship?'),
        ]]),
        'donna.test/api/v1/messages/2' => Http::sequence()
            ->push(['data' => apiMessage(2, 'donna', '', ['status' => 'pending'])])
            ->push(['data' => apiMessage(2, 'donna', 'Shipped an hour ago.')]),
    ]);

    artisan('donna:chat')
        ->expectsOutputToContain('Shipped an hour ago.')
        ->expectsQuestion('You', '/quit')
        ->assertSuccessful();

    Sleep::assertSleptTimes(1);
});

it('sends what Josh types and prints Donna\'s reply, with sources, once she finishes', function () {
    Http::fake([
        'donna.test/api/v1/messages?limit=10' => Http::response(['data' => []]),
        'donna.test/api/v1/messages' => Http::response(['data' => [
            'message' => apiMessage(1, 'josh', 'What did the Fed do?'),
            'reply' => apiMessage(2, 'donna', '', ['status' => 'pending', 'in_reply_to_id' => 1]),
        ]], 202),
        'donna.test/api/v1/messages/2' => Http::sequence()
            ->push(['data' => apiMessage(2, 'donna', '', ['status' => 'pending'])])
            ->push(['data' => apiMessage(2, 'donna', 'Held rates.', [
                'citations' => [['url' => 'https://example.com/fed', 'title' => 'Fed holds']],
            ])]),
    ]);

    artisan('donna:chat')
        ->expectsQuestion('You', 'What did the Fed do?')
        ->expectsOutputToContain('Held rates.')
        ->expectsOutputToContain('https://example.com/fed')
        ->expectsQuestion('You', '/quit')
        ->assertSuccessful();

    Http::assertSent(fn (Request $request): bool => $request->method() === 'POST'
        && $request->url() === 'https://donna.test/api/v1/messages'
        && $request->data() === ['body' => 'What did the Fed do?', 'source' => 'terminal']);
});

it('shows why when a reply failed instead of leaving Josh waiting', function () {
    Http::fake([
        'donna.test/api/v1/messages?limit=10' => Http::response(['data' => []]),
        'donna.test/api/v1/messages' => Http::response(['data' => [
            'message' => apiMessage(1, 'josh', 'Hello?'),
            'reply' => apiMessage(2, 'donna', '', ['status' => 'pending']),
        ]], 202),
        'donna.test/api/v1/messages/2' => Http::response(['data' => apiMessage(2, 'donna', '', [
            'status' => 'failed', 'error' => 'Donna could not reply. Your message is saved; send it again in a moment.',
        ])]),
    ]);

    artisan('donna:chat')
        ->expectsQuestion('You', 'Hello?')
        ->expectsOutputToContain('Donna could not reply. Your message is saved; send it again in a moment.')
        ->expectsQuestion('You', '/quit')
        ->assertSuccessful();
});

it('flags Donna\'s last reply with a slash command, without sending it to her', function (string $input, string $rating, ?string $note) {
    Http::fake([
        'donna.test/api/v1/messages?limit=10' => Http::response(['data' => [
            apiMessage(2, 'donna', 'It shipped.'),
            apiMessage(1, 'josh', 'Did the order ship?'),
        ]]),
        'donna.test/api/v1/messages/2/evaluation' => Http::response(['data' => ['message_id' => 2, 'rating' => $rating, 'note' => $note]], 201),
    ]);

    artisan('donna:chat')
        ->expectsQuestion('You', $input)
        ->expectsQuestion('You', '/quit')
        ->assertSuccessful();

    Http::assertSent(fn (Request $request): bool => $request->url() === 'https://donna.test/api/v1/messages/2/evaluation'
        && $request->data() === ['rating' => $rating, 'note' => $note]);
    Http::assertNotSent(fn (Request $request): bool => $request->url() === 'https://donna.test/api/v1/messages');
})->with([
    'good with a note' => ['/good exactly the right length', 'good', 'exactly the right length'],
    'bad without one' => ['/bad', 'bad', null],
]);

it('shows more history on request', function () {
    Http::fake([
        'donna.test/api/v1/messages?limit=10' => Http::response(['data' => []]),
        'donna.test/api/v1/messages?limit=30' => Http::response(['data' => [apiMessage(1, 'josh', 'From a while back.')]]),
    ]);

    artisan('donna:chat')
        ->expectsQuestion('You', '/history 30')
        ->expectsOutputToContain('From a while back.')
        ->expectsQuestion('You', '/quit')
        ->assertSuccessful();
});

it('explains what to set when the API URL or token is missing', function () {
    config(['donna.client.token' => null]);

    artisan('donna:chat')
        ->expectsOutputToContain('Set DONNA_API_URL and DONNA_API_TOKEN')
        ->assertFailed();

    Http::assertNothingSent();
});

it('says the token was rejected rather than dumping an error', function () {
    Http::fake(['donna.test/api/v1/messages?limit=10' => Http::response(['message' => 'Unauthenticated.'], 401)]);

    artisan('donna:chat')
        ->expectsOutputToContain('Donna did not accept the token. Check DONNA_API_TOKEN.')
        ->assertFailed();
});
