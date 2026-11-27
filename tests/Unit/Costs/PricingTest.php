<?php

use App\Costs\Pricing;
use Tests\TestCase;

uses(TestCase::class);

it('prices uncached input, cache writes, cache reads and output at their own rates', function () {
    // 10,000 prompt tokens: 6,000 read from cache, 1,000 written, 3,000 uncached.
    $usd = (new Pricing)->price(
        'claude-sonnet-5-5',
        inputTokens: 10_000,
        outputTokens: 2_000,
        cacheReadTokens: 6_000,
        cacheWriteTokens: 1_000,
    );

    // 3,000 × $2 + 1,000 × $2.50 + 6,000 × $0.10 + 2,000 × $10, per million.
    expect($usd)->toBe('0.029100');
});

it('halves token cost for batched calls', function () {
    $usd = (new Pricing)->price('claude-sonnet-5-5', inputTokens: 100_000, outputTokens: 10_000, batched: true);

    expect($usd)->toBe('0.150000');
});

it('adds a flat charge for each web search', function () {
    $usd = (new Pricing)->price('claude-sonnet-5-5', inputTokens: 1_000, outputTokens: 0, webSearchRequests: 3);

    expect($usd)->toBe('0.032000');
});

it('prices a prompt on the long-context card only once it passes the threshold', function (int $inputTokens, string $expected) {
    $usd = (new Pricing)->price('claude-haiku-5-5', inputTokens: $inputTokens, outputTokens: 1_000);

    expect($usd)->toBe($expected);
})->with([
    'at the threshold' => [100_000, '0.010500'],
    'over the threshold' => [200_000, '0.102500'],
]);

it('prices a dated model ID as its undated entry', function () {
    $pricing = new Pricing;

    // The two Sonnets differ only in their cache-read rate.
    expect($pricing->price('claude-sonnet-5-5-20261001', 1_000_000, 0, cacheReadTokens: 1_000_000))->toBe('0.100000')
        ->and($pricing->price('claude-sonnet-5-20260601', 1_000_000, 0, cacheReadTokens: 1_000_000))->toBe('0.200000');
});

it('returns null for a model with no pricing entry', function () {
    expect((new Pricing)->price('some-other-model', 1_000, 1_000))->toBeNull();
});
