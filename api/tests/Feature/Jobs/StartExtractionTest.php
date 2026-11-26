<?php

use App\Enums\ExtractionRunStatus;
use App\Jobs\StartExtraction;
use App\Models\ExtractionRun;
use App\Models\Fact;
use App\Models\Message;
use App\Models\Person;
use App\Models\Topic;

use function Pest\Laravel\travelTo;

it('backfills everything since the first message on the first run, one request per day', function () {
    donnaExists();
    $batches = fakeBatches();
    // 10pm on the 23rd and 9am on the 24th, Chicago time.
    $first = Message::factory()->create(['body' => 'Fibermade pricing is going to $29.', 'created_at' => '2026-11-24 04:00:00']);
    Message::factory()->replyTo($first)->create(['body' => 'About time.', 'created_at' => '2026-11-24 04:00:05']);
    Message::factory()->create(['body' => 'Kristen wants to do the Houston show.', 'created_at' => '2026-11-24 15:00:00']);
    travelTo('2026-11-25 09:00:00');

    StartExtraction::dispatchSync();

    $run = ExtractionRun::sole();
    $requests = $batches->submitted[$run->batch_id];

    expect($run)
        ->status->toBe(ExtractionRunStatus::Submitted)
        ->window_start->toDateTimeString()->toBe('2026-11-24 04:00:00')
        ->window_end->toDateTimeString()->toBe('2026-11-25 08:59:59')
        ->and(array_column($requests, 'customId'))->toBe(['day-2026-11-23', 'day-2026-11-24'])
        ->and($requests[0])
        ->model->toBe('claude-sonnet-5-5')
        ->prompt->toContain('Fibermade pricing is going to $29.', 'About time.')
        ->prompt->not->toContain('Kristen wants to do the Houston show.')
        ->and($requests[1]->prompt)->toContain('Kristen wants to do the Houston show.');
});

it('picks up where the last applied run ended', function () {
    donnaExists();
    $batches = fakeBatches();
    ExtractionRun::factory()->applied()->create(['window_end' => '2026-11-24 09:00:00']);
    Message::factory()->create(['body' => 'Already extracted.', 'created_at' => '2026-11-24 09:00:00']);
    Message::factory()->create(['body' => 'New since then.', 'created_at' => '2026-11-24 09:00:01']);
    travelTo('2026-11-25 09:00:00');

    StartExtraction::dispatchSync();

    $run = ExtractionRun::where('status', ExtractionRunStatus::Submitted)->sole();

    expect($run->window_start->toDateTimeString())->toBe('2026-11-24 09:00:00')
        ->and($batches->submitted[$run->batch_id])->toHaveCount(1)
        ->and($batches->submitted[$run->batch_id][0]->prompt)
        ->toContain('New since then.')
        ->not->toContain('Already extracted.');
});

it('gives each day the people Donna knows and the facts that day could change, by number', function () {
    donnaExists();
    $batches = fakeBatches();
    Person::factory()->create(['name' => 'Kristen', 'relationship' => 'wife']);
    $topic = Topic::factory()->create(['name' => 'Fibermade pricing']);
    $related = Fact::factory()->hasAttached($topic, relationship: 'topics')->create(['statement' => 'Fibermade pricing is $19 a month.']);
    Fact::factory()->hasAttached(Topic::factory()->create(), relationship: 'topics')->create(['statement' => 'The gate code is 4471.']);
    Message::factory()->hasAttached($topic)->create(['body' => 'Going to $29.', 'created_at' => '2026-11-24 15:00:00']);
    travelTo('2026-11-25 09:00:00');

    StartExtraction::dispatchSync();

    expect(collect($batches->submitted)->sole()[0]->prompt)
        ->toContain('Kristen', "topic {$topic->id}: Fibermade pricing", "#{$related->id}", 'Fibermade pricing is $19 a month.')
        ->not->toContain('The gate code is 4471.');
});

it('leaves an exchange Donna is still answering whole for the next run', function () {
    donnaExists();
    $batches = fakeBatches();
    Message::factory()->create(['body' => 'Settled earlier.', 'created_at' => '2026-11-25 08:00:00']);
    $asked = Message::factory()->create(['body' => 'Asked a moment ago.', 'created_at' => '2026-11-25 08:59:50']);
    Message::factory()->pending()->create(['in_reply_to_id' => $asked->id, 'created_at' => '2026-11-25 08:59:50']);
    travelTo('2026-11-25 09:00:00');

    StartExtraction::dispatchSync();

    $run = ExtractionRun::sole();

    expect($run->window_end->toDateTimeString())->toBe('2026-11-25 08:59:49')
        ->and($batches->submitted[$run->batch_id][0]->prompt)
        ->toContain('Settled earlier.')
        ->not->toContain('Asked a moment ago.');
});

it('puts facts about the day\'s topics ahead of newer facts about Josh when space is short', function () {
    ['josh' => $josh] = donnaExists();
    $batches = fakeBatches();
    config(['donna.extraction.related_facts_per_request' => 1]);
    $topic = Topic::factory()->create(['name' => 'Fibermade pricing']);
    Fact::factory()->hasAttached($topic, relationship: 'topics')->create(['statement' => 'Fibermade pricing is $19 a month.']);
    Fact::factory()->hasAttached($josh, relationship: 'people')->create(['statement' => 'Josh bought new running shoes.']);
    Message::factory()->hasAttached($topic)->create(['body' => 'Going to $29.', 'created_at' => '2026-11-24 15:00:00']);
    travelTo('2026-11-25 09:00:00');

    StartExtraction::dispatchSync();

    expect(collect($batches->submitted)->sole()[0]->prompt)
        ->toContain('Fibermade pricing is $19 a month.')
        ->not->toContain('Josh bought new running shoes.');
});

it('submits nothing when nothing has been said since the last run', function () {
    $batches = fakeBatches();
    ExtractionRun::factory()->applied()->create(['window_end' => '2026-11-24 09:00:00']);
    Message::factory()->create(['created_at' => '2026-11-24 08:00:00']);
    travelTo('2026-11-25 09:00:00');

    StartExtraction::dispatchSync();

    expect($batches->submitted)->toBeEmpty()
        ->and(ExtractionRun::count())->toBe(1);
});

it('waits while an earlier run is still in flight', function () {
    $batches = fakeBatches();
    ExtractionRun::factory()->create();
    Message::factory()->create();
    travelTo(now()->addDay());

    StartExtraction::dispatchSync();

    expect($batches->submitted)->toBeEmpty();
});
