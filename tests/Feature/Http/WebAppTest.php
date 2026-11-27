<?php

use function Pest\Laravel\get;
use function Pest\Laravel\getJson;
use function Pest\Laravel\withoutVite;

beforeEach(fn () => withoutVite());

it('serves the web app at every address that is not the API, locked down against other sites', function (string $path) {
    $response = get($path)->assertOk()->assertViewIs('app');

    expect($response->headers->get('Content-Security-Policy'))
        ->toContain("default-src 'self'", "script-src 'self'", "frame-ancestors 'none'")
        ->not->toContain('unsafe-inline')
        ->and($response->headers->get('X-Frame-Options'))->toBe('DENY')
        ->and($response->headers->get('X-Content-Type-Options'))->toBe('nosniff')
        // "same-origin", not "no-referrer": Sanctum recognises the app's own requests by it.
        ->and($response->headers->get('Referrer-Policy'))->toBe('same-origin');
})->with([
    'the chat' => ['/'],
    'a page inside it' => ['/login/link/'.str_repeat('a', 40)],
]);

it('leaves the API and the health check to themselves', function () {
    getJson('/api/v1/nothing-here')->assertNotFound()->assertJsonStructure(['message']);
    get('/api')->assertNotFound();
    get('/build/assets/missing.js')->assertNotFound();
    get('/up')->assertOk()->assertDontSee('id="app"', false);
});
