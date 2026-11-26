<?php

use App\Enums\FactKind;
use App\Enums\FactProvenance;
use App\Models\Fact;
use App\Models\Person;

use function Pest\Laravel\travelTo;

it('supersedes the fact with the correction and keeps the old one as history', function () {
    donnaExists();
    fakeEmbeddings();
    travelTo('2026-11-26 15:00:00');
    $kristen = Person::factory()->create(['name' => 'Kristen']);
    $old = Fact::factory()->hasAttached($kristen, relationship: 'people')->create([
        'statement' => 'Kristen\'s main project is Storyrift.',
        'kind' => FactKind::Decision,
    ]);

    donnaCalls([['correct_fact', [
        'fact_id' => $old->id,
        'statement' => 'Kristen\'s main project is Bad Frog.',
        'notable_change' => true,
    ]]]);

    $new = Fact::query()->active()->sole();

    expect($new)
        ->statement->toBe('Kristen\'s main project is Bad Frog.')
        ->kind->toBe(FactKind::Decision)
        ->provenance->toBe(FactProvenance::Correction)
        ->notable_change->toBeTrue()
        ->embedding->not->toBeNull()
        ->and($new->people->modelKeys())->toBe([$kristen->id])
        ->and($old->refresh())
        ->statement->toBe('Kristen\'s main project is Storyrift.')
        ->superseded_by_id->toBe($new->id)
        ->superseded_at->toDateTimeString()->toBe('2026-11-26 15:00:00');
});

it('refuses to correct a fact that has already been replaced', function () {
    donnaExists();
    fakeEmbeddings();
    $current = Fact::factory()->create();
    $old = Fact::factory()->supersededBy($current)->create();

    [$result] = donnaCalls([['correct_fact', ['fact_id' => $old->id, 'statement' => 'Something else.']]]);

    expect($result)->toContain("fact #{$current->id}")
        ->and(Fact::count())->toBe(2);
});
