<?php

use App\Models\PushSubscription;
use App\Models\User;
use Illuminate\Support\Facades\DB;

use function Pest\Laravel\getJson;
use function Pest\Laravel\postJson;
use function Pest\Laravel\travel;

/**
 * Sign in on "this device" with a fresh emailed code.
 */
function signIn(User $user): void
{
    fromWebApp();

    ['code' => $code] = requestSignInEmail($user->email);

    postJson('/api/v1/auth/verify', ['email' => $user->email, 'code' => $code])->assertOk();
}

/**
 * The "Sign out everywhere" request behind the link in the newest email:
 * what the page's one button posts.
 */
function revokeRequestFromEmail(): string
{
    [$expires, $signature] = explode('-', str(sentEmails()->last()->getTextBody())->match('#/security/revoke/([\w-]+)#')->value());

    return "/api/v1/auth/revoke?expires={$expires}&signature={$signature}";
}

describe('signing in', function () {
    it('starts a session from the emailed code that works like a token, once', function () {
        ['user' => $user] = donnaExists();
        fromWebApp();
        ['code' => $code] = requestSignInEmail($user->email);

        postJson('/api/v1/auth/verify', ['email' => $user->email, 'code' => substr($code, 0, 3).' '.substr($code, 3)])
            ->assertOk()
            ->assertJsonPath('data.name', $user->name)
            ->assertJsonPath('data.timezone', config('donna.timezone'));

        getJson('/api/v1/messages')->assertOk();

        postJson('/api/v1/auth/verify', ['email' => $user->email, 'code' => $code])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['code' => "That code didn't work. Check it, or ask for a new one."]);
    });

    it('starts a session from the emailed link, once', function () {
        ['user' => $user] = donnaExists();
        fromWebApp();
        ['token' => $token] = requestSignInEmail($user->email);

        postJson('/api/v1/auth/verify-link', ['token' => $token])
            ->assertOk()
            ->assertJsonPath('data.name', $user->name);

        getJson('/api/v1/messages')->assertOk();

        postJson('/api/v1/auth/verify-link', ['token' => $token])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['token' => 'That link has expired or was already used. Ask for a new one.']);
    });

    it('refuses a code or link that should no longer work', function (Closure $spoil) {
        ['user' => $user] = donnaExists();
        fromWebApp();
        $login = requestSignInEmail($user->email);

        $spoil($user);

        postJson('/api/v1/auth/verify', ['email' => $user->email, 'code' => $login['code']])->assertUnprocessable();
        postJson('/api/v1/auth/verify-link', ['token' => $login['token']])->assertUnprocessable();
        getJson('/api/v1/messages')->assertUnauthorized();
    })->with([
        'after ten minutes' => [fn () => travel(11)->minutes()],
        'after a newer one was requested' => [fn (User $user) => requestSignInEmail($user->email)],
        'after five wrong guesses' => [function (User $user) {
            foreach (range(1, 5) as $guess) {
                postJson('/api/v1/auth/verify', ['email' => $user->email, 'code' => '00000'.$guess])->assertUnprocessable();
            }
        }],
    ]);

    it('emails Josh about every sign-in, naming the device', function () {
        ['user' => $user] = donnaExists();
        test()->withHeader('User-Agent', 'Mozilla/5.0 (iPhone; CPU iPhone OS 26_1 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/26.1 Mobile/15E148 Safari/604.1');

        signIn($user);

        $email = sentEmails()->last();

        expect($email->getTo()[0]->getAddress())->toBe($user->email)
            ->and($email->getSubject())->toBe('New sign-in to Donna')
            ->and($email->getTextBody())
            ->toContain('Safari on iPhone')
            ->toContain('127.0.0.1')
            ->toContain(config('app.url').'/security/revoke/');
    });
});

describe('sessions ending', function () {
    it('ends a session 90 days after sign-in even while it is being used', function () {
        ['user' => $user] = donnaExists();
        signIn($user);

        // Used every 20 days, so the 30-day idle timeout never applies.
        foreach (range(1, 4) as $visit) {
            travel(20)->days();
            getJson('/api/v1/messages')->assertOk();
        }

        travel(20)->days();

        getJson('/api/v1/messages')->assertUnauthorized();
    });

    it('signs this device out', function () {
        ['user' => $user] = donnaExists();
        signIn($user);

        postJson('/api/v1/auth/logout')->assertNoContent();

        getJson('/api/v1/messages')->assertUnauthorized();
    });

    it('signs every device out and stops their notifications, leaving the terminal\'s token alone', function () {
        config(['session.driver' => 'database']);
        ['user' => $user] = donnaExists();
        $token = $user->createToken('terminal')->plainTextToken;
        PushSubscription::factory()->for($user)->create();
        DB::table('sessions')->insert(['id' => 'another-device', 'user_id' => $user->id, 'payload' => '', 'last_activity' => now()->getTimestamp()]);
        signIn($user);

        postJson('/api/v1/auth/logout-everywhere')->assertNoContent();

        expect(DB::table('sessions')->where('user_id', $user->id)->count())->toBe(0)
            ->and(PushSubscription::count())->toBe(0);

        getJson('/api/v1/messages')->assertUnauthorized();
        test()->flushHeaders()->getJson('/api/v1/messages', ['Authorization' => "Bearer {$token}"])->assertOk();
    });
});

describe('the "sign out everywhere" link in the new-sign-in email', function () {
    it('ends every session when its button is pressed, from a device that is not signed in', function () {
        config(['session.driver' => 'database']);
        ['user' => $user] = donnaExists();
        signIn($user);
        $revoke = revokeRequestFromEmail();
        DB::table('sessions')->insert(['id' => 'the-thief', 'user_id' => $user->id, 'payload' => '', 'last_activity' => now()->getTimestamp()]);

        postJson($revoke)->assertNoContent();

        expect(DB::table('sessions')->where('user_id', $user->id)->count())->toBe(0);
        getJson('/api/v1/messages')->assertUnauthorized();
    });

    it('does nothing without a valid, unexpired signature', function (Closure $spoil) {
        ['user' => $user] = donnaExists();
        signIn($user);

        postJson($spoil(revokeRequestFromEmail()))->assertForbidden();

        getJson('/api/v1/messages')->assertOk();
    })->with([
        'a forged signature' => [fn (string $url) => preg_replace('/signature=\w{8}/', 'signature=deadbeef', $url)],
        'a link older than seven days' => [function (string $url) {
            travel(8)->days();

            return $url;
        }],
    ]);
});
