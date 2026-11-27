<?php

use App\Models\Fact;
use App\Models\Person;
use Illuminate\Support\Facades\DB;

it('deletes the fact, every earlier version of it, and their links — and nothing else', function () {
    ['josh' => $josh] = donnaExists();
    fakeEmbeddings();
    $current = Fact::factory()->hasAttached($josh, relationship: 'people')->create();
    $previous = Fact::factory()->hasAttached($josh, relationship: 'people')->supersededBy($current)->create();
    Fact::factory()->supersededBy($previous)->create();
    $unrelated = Fact::factory()->hasAttached($josh, relationship: 'people')->create();

    [$result] = donnaCalls([['forget_fact', ['fact_id' => $current->id]]]);

    expect(Fact::pluck('id')->all())->toBe([$unrelated->id])
        ->and(DB::table('fact_subjects')->pluck('fact_id')->all())->toBe([$unrelated->id])
        ->and(Person::josh()->is($josh))->toBeTrue()
        ->and($result)->toContain('2 earlier version(s)');
});
