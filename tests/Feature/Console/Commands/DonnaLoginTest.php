<?php

use Illuminate\Support\Facades\Artisan;

use function Pest\Laravel\getJson;
use function Pest\Laravel\postJson;

it('prints a code and a link that sign Josh in when email is not working', function (Closure $signInWith) {
    ['user' => $user] = donnaExists();
    fromWebApp();

    Artisan::call('donna:login');

    $signInWith(Artisan::output(), $user->email)->assertOk();

    getJson('/api/v1/messages')->assertOk();
    expect(sentEmails()->pluck('subject'))->not->toContain('is your Donna sign-in code');
})->with([
    'the code' => [fn (string $output, string $email) => postJson('/api/v1/auth/verify', [
        'email' => $email,
        'code' => str($output)->match('/Code: (\d{6})/')->value(),
    ])],
    'the link' => [fn (string $output) => postJson('/api/v1/auth/verify-link', [
        'token' => str($output)->match('#/login/link/(\w{40})#')->value(),
    ])],
]);
