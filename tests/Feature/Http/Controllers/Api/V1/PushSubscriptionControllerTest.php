<?php

use App\Models\PushSubscription;
use App\Models\User;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\deleteJson;
use function Pest\Laravel\postJson;

/**
 * A browser's subscription, as `PushSubscription.toJSON()` gives it.
 *
 * @return array<string, mixed>
 */
function browserSubscription(string $endpoint = 'https://web.push.apple.com/QGuS-abc123'): array
{
    return ['endpoint' => $endpoint, 'keys' => ['p256dh' => 'BPublicKey', 'auth' => 'authSecret']];
}

it('returns 401 without a token or a session', function (string $method) {
    $this->json($method, '/api/v1/push-subscriptions', browserSubscription())->assertUnauthorized();
    $this->json('DELETE', '/api/v1/presence')->assertUnauthorized();
})->with(['POST', 'DELETE']);

it('subscribes the device making the request, and only updates it when it asks again', function () {
    $user = User::factory()->create();
    actingAs($user);

    postJson('/api/v1/push-subscriptions', browserSubscription(), ['User-Agent' => 'Safari on an iPhone'])->assertNoContent();
    postJson('/api/v1/push-subscriptions', [...browserSubscription(), 'keys' => ['p256dh' => 'BRenewedKey', 'auth' => 'authSecret']])->assertNoContent();

    expect(PushSubscription::sole())
        ->user_id->toBe($user->id)
        ->endpoint->toBe('https://web.push.apple.com/QGuS-abc123')
        ->public_key->toBe('BRenewedKey')
        ->auth_token->toBe('authSecret')
        ->last_used_at->not->toBeNull();
});

it('unsubscribes only the device making the request', function () {
    $user = User::factory()->create();
    $phone = PushSubscription::factory()->for($user)->create();
    $mac = PushSubscription::factory()->for($user)->create();
    actingAs($user);

    deleteJson('/api/v1/push-subscriptions', ['endpoint' => $phone->endpoint])->assertNoContent();

    expect(PushSubscription::sole()->is($mac))->toBeTrue();
});

// The server posts to whatever address a subscription holds, so it has to be a push service's.
it('refuses a subscription that does not point at a push service', function (string $endpoint) {
    actingAs(User::factory()->create());

    postJson('/api/v1/push-subscriptions', browserSubscription($endpoint))
        ->assertUnprocessable()
        ->assertJsonValidationErrors('endpoint');

    expect(PushSubscription::count())->toBe(0);
})->with([
    'another site' => ['https://attacker.example/collect'],
    'a lookalike host' => ['https://evilpush.apple.com.attacker.example/x'],
    'plain http' => ['http://web.push.apple.com/QGuS-abc123'],
    'the server itself' => ['https://127.0.0.1/admin'],
]);
