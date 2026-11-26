<?php

use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Sleep;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| Feature tests run against Postgres with pgvector, so vector and full-text
| queries are the real thing. Nothing reaches the network and nothing sleeps.
|
*/

pest()->extend(TestCase::class)
    ->use(LazilyRefreshDatabase::class)
    ->beforeEach(function () {
        Http::preventStrayRequests();
        Sleep::fake(syncWithCarbon: true);
    })
    ->in('Feature');

/*
|--------------------------------------------------------------------------
| Helpers
|--------------------------------------------------------------------------
*/

/**
 * A unit vector along one axis of the embedding space.
 *
 * Two texts given the same axis are identical to pgvector; different axes
 * are unrelated. Tests use it to decide what is "about" what.
 *
 * @return list<float>
 */
function embedding(int $axis): array
{
    $vector = array_fill(0, config('donna.models.embedding.dimensions'), 0.0);
    $vector[$axis] = 1.0;

    return $vector;
}
