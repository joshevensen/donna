<?php

use function Pest\Laravel\postJson;
use function Pest\Laravel\travel;

it('emails Josh one message with a code and a link', function () {
    ['user' => $user] = donnaExists();

    postJson('/api/v1/auth/login-code', ['email' => strtoupper($user->email)])
        ->assertAccepted();

    $email = sentEmails()->sole();

    expect($email->getTo()[0]->getAddress())->toBe($user->email)
        ->and($email->getSubject())->toMatch('/^\d{6} is your Donna sign-in code$/')
        ->and($email->getTextBody())
        ->toContain(str($email->getSubject())->before(' ')->value())
        ->toMatch('#'.preg_quote(config('app.url'), '#').'/login/link/\w{40}#');
});

it('answers the same way for any other address, and emails nobody', function () {
    donnaExists();

    postJson('/api/v1/auth/login-code', ['email' => 'someone@example.com'])
        ->assertAccepted();

    expect(sentEmails())->toBeEmpty();
});

it('stops sending codes to one address after three requests in ten minutes', function () {
    ['user' => $user] = donnaExists();

    foreach (range(1, 3) as $request) {
        postJson('/api/v1/auth/login-code', ['email' => $user->email])->assertAccepted();
    }

    postJson('/api/v1/auth/login-code', ['email' => $user->email])->assertTooManyRequests();
    expect(sentEmails())->toHaveCount(3);

    travel(11)->minutes();

    postJson('/api/v1/auth/login-code', ['email' => $user->email])->assertAccepted();
});

it('stops answering one IP address after ten requests in an hour, whatever addresses it tries', function () {
    donnaExists();

    foreach (range(1, 10) as $request) {
        postJson('/api/v1/auth/login-code', ['email' => "guess{$request}@example.com"])->assertAccepted();
    }

    postJson('/api/v1/auth/login-code', ['email' => 'guess11@example.com'])->assertTooManyRequests();
});
