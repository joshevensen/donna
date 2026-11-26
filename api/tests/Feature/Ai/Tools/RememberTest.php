<?php

use App\Enums\FactKind;
use App\Enums\FactProvenance;
use App\Models\Fact;
use App\Models\Message;
use App\Models\Person;
use App\Models\Topic;

it('writes the fact to memory immediately, about Josh unless told otherwise', function () {
    ['josh' => $josh] = donnaExists();
    fakeEmbeddings(['anniversary' => 4]);

    [$result] = donnaCalls([['remember', ['statement' => 'Josh and Kristen\'s anniversary is June 3.']]]);

    $fact = Fact::sole();

    expect($fact)
        ->statement->toBe('Josh and Kristen\'s anniversary is June 3.')
        ->kind->toBe(FactKind::Fact)
        ->provenance->toBe(FactProvenance::Remember)
        ->confidence->toBe(1.0)
        ->embedding->toBe(embedding(4))
        ->isActive()->toBeTrue()
        ->and($fact->sourceMessage->is(Message::first()))->toBeTrue()
        ->and($fact->people->modelKeys())->toBe([$josh->id])
        ->and($result)->toBe("Remembered as fact #{$fact->id}.");
});

it('links the fact to who and what it is about, meeting new people and topics as it goes', function () {
    donnaExists();
    fakeEmbeddings();
    $kristen = Person::factory()->create(['name' => 'Kristen', 'aliases' => ['Kris']]);

    [$result] = donnaCalls([['remember', [
        'statement' => 'Kristen and Dana are sharing a booth at the Houston yarn show.',
        'kind' => 'decision',
        'subjects' => [
            ['type' => 'person', 'name' => 'kris'],
            ['type' => 'person', 'name' => 'Dana'],
            ['type' => 'topic', 'name' => 'Houston yarn show'],
            ['type' => 'project', 'name' => 'Untracked Thing'],
        ],
    ]]]);

    $fact = Fact::sole();

    expect($fact->kind)->toBe(FactKind::Decision)
        ->and($fact->people->pluck('name')->all())->toEqualCanonicalizing(['Kristen', 'Dana'])
        ->and($fact->people->contains($kristen))->toBeTrue()
        ->and($fact->topics->pluck('name')->all())->toBe(['Houston yarn show'])
        ->and(Topic::sole()->embedding)->toBe(embedding(0))
        ->and($fact->projects)->toBeEmpty()
        ->and($result)->toContain('Untracked Thing');
});

it('writes a fact once when a retried reply repeats the call', function () {
    donnaExists();
    fakeEmbeddings();
    $call = ['remember', ['statement' => 'The gate code is 4471.']];

    donnaCalls([$call, $call]);

    expect(Fact::count())->toBe(1);
});
