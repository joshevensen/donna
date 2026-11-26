<?php

use Illuminate\Support\Facades\Artisan;
use Laravel\Sanctum\PersonalAccessToken;

use function Pest\Laravel\getJson;

it('creates a token for Josh that the API accepts', function () {
    ['user' => $user] = donnaExists();

    Artisan::call('donna:token', ['name' => 'macbook']);

    $token = str(Artisan::output())->match('/^\d+\|\w+$/m')->value();

    expect(PersonalAccessToken::sole())
        ->name->toBe('macbook')
        ->tokenable_id->toBe($user->id);

    getJson('/api/v1/messages', ['Authorization' => "Bearer {$token}"])->assertOk();
});
