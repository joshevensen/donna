<?php

use App\Enums\CostPurpose;
use App\Models\Cost;

use function Pest\Laravel\travelTo;

it('totals the month in Josh\'s timezone, broken down by what the money went on', function () {
    donnaExists();
    fakeEmbeddings();
    travelTo('2026-11-26 15:00:00');
    Cost::factory()->create(['purpose' => CostPurpose::Reply, 'usd' => '1.250000', 'created_at' => '2026-11-10 12:00:00']);
    Cost::factory()->create(['purpose' => CostPurpose::Reply, 'usd' => '0.500000', 'web_search_requests' => 2, 'created_at' => '2026-11-20 12:00:00']);
    Cost::factory()->create(['purpose' => CostPurpose::Extraction, 'usd' => '0.250000', 'created_at' => '2026-11-21 09:00:00']);
    // 3am UTC on the 1st is still October 31st in Chicago.
    Cost::factory()->create(['purpose' => CostPurpose::Reply, 'usd' => '9.000000', 'created_at' => '2026-11-01 03:00:00']);

    [$result] = donnaCalls([['cost_report', ['period' => 'this_month']]], said: 'What did you cost me this month?');

    // The exchange asking the question is itself two calls: an embedding and this reply so far.
    expect($result)
        ->toContain('2026-11-01 to 2026-11-30', '$2.00 across 5 model calls, including 2 web searches')
        ->toContain('- reply: $1.75 (3 calls)', '- extraction: $0.25 (1 call)', '- embedding: $0.00 (1 call)');
});

it('reports last month separately from this one', function () {
    donnaExists();
    fakeEmbeddings();
    travelTo('2026-11-26 15:00:00');
    Cost::factory()->create(['usd' => '4.000000', 'created_at' => '2026-10-15 12:00:00']);
    Cost::factory()->create(['usd' => '1.000000', 'created_at' => '2026-11-15 12:00:00']);

    [$result] = donnaCalls([['cost_report', ['period' => 'last_month']]]);

    expect($result)->toContain('2026-10-01 to 2026-10-31', '$4.00 across 1 model call, including 0 web searches');
});
