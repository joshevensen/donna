<?php

use App\Enums\PersonKind;
use App\Models\Person;

it('adds someone new to Josh\'s orbit', function () {
    donnaExists();
    fakeEmbeddings();

    donnaCalls([['save_person', [
        'name' => 'Sarah Lark',
        'aliases' => ['Sarah'],
        'relationship' => 'yarn vendor',
        'summary' => 'Runs Lark & Loom. Reliable, slow to invoice.',
    ]]]);

    expect(Person::where('name', 'Sarah Lark')->sole())
        ->kind->toBe(PersonKind::Other)
        ->aliases->toBe(['Sarah'])
        ->relationship->toBe('yarn vendor')
        ->summary->toBe('Runs Lark & Loom. Reliable, slow to invoice.');
});

it('updates someone it already knows, found by alias, leaving the rest alone', function () {
    donnaExists();
    fakeEmbeddings();
    $kristen = Person::factory()->create(['name' => 'Kristen', 'aliases' => ['Kris'], 'relationship' => 'wife', 'summary' => 'Runs Bad Frog.']);

    donnaCalls([['save_person', ['name' => 'kris', 'aliases' => ['K', 'Kris'], 'summary' => 'Runs Bad Frog; training for a half marathon.']]]);

    expect(Person::where('kind', PersonKind::Other)->sole())
        ->id->toBe($kristen->id)
        ->name->toBe('Kristen')
        ->aliases->toBe(['Kris', 'K'])
        ->relationship->toBe('wife')
        ->summary->toBe('Runs Bad Frog; training for a half marathon.');
});
