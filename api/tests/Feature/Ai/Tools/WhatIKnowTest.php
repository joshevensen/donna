<?php

use App\Models\Fact;
use App\Models\Person;
use App\Models\Project;

it('lists the active facts about a person, without what has been superseded', function () {
    donnaExists();
    fakeEmbeddings();
    $kristen = Person::factory()->create(['name' => 'Kristen', 'summary' => 'Josh\'s wife; runs Bad Frog.']);
    $current = Fact::factory()->hasAttached($kristen, relationship: 'people')->create(['statement' => 'Kristen dyes yarn on Tuesdays.']);
    Fact::factory()->hasAttached($kristen, relationship: 'people')->supersededBy($current)->create(['statement' => 'Kristen dyes yarn on Mondays.']);
    Fact::factory()->hasAttached(Person::factory()->create(), relationship: 'people')->create(['statement' => 'Mark owes Josh lunch.']);

    [$result] = donnaCalls([['what_i_know', ['type' => 'person', 'name' => 'kristen']]]);

    expect($result)
        ->toContain('Josh\'s wife; runs Bad Frog.', '] Kristen dyes yarn on Tuesdays.')
        ->not->toContain('] Kristen dyes yarn on Mondays.', 'Mark owes Josh lunch.');
});

it('answers "what do you know about me" from the facts about Josh', function () {
    ['josh' => $josh] = donnaExists();
    fakeEmbeddings();
    Fact::factory()->hasAttached($josh, relationship: 'people')->create(['statement' => 'Josh has ADHD.']);

    [$result] = donnaCalls([['what_i_know', ['type' => 'person', 'name' => 'me']]]);

    expect($result)->toContain('Josh has ADHD.');
});

it('lists what is known about a project by any of its names', function () {
    donnaExists();
    fakeEmbeddings();
    $project = Project::factory()->create(['name' => 'Fibermade', 'aliases' => ['FM']]);
    Fact::factory()->hasAttached($project, relationship: 'projects')->create(['statement' => 'Fibermade launched in 2025.']);

    [$result] = donnaCalls([['what_i_know', ['type' => 'project', 'name' => 'fm']]]);

    expect($result)->toContain('Fibermade launched in 2025.');
});

it('says so when there is nobody by that name', function () {
    donnaExists();
    fakeEmbeddings();

    [$result] = donnaCalls([['what_i_know', ['type' => 'person', 'name' => 'Zed']]]);

    expect($result)->toBe('You have no person called Zed.')
        ->and(Person::where('name', 'Zed')->exists())->toBeFalse();
});
