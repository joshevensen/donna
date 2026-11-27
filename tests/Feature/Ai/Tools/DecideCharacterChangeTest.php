<?php

use App\Enums\CharacterProfileStatus;
use App\Models\CharacterProfile;
use App\Models\Message;

use function Pest\Laravel\travelTo;

it('makes the proposed profile who Donna is once Josh approves', function () {
    donnaExists();
    fakeEmbeddings();
    $previous = CharacterProfile::current();
    $proposal = CharacterProfile::factory()->proposed()->create(['proposed_in_message_id' => Message::factory()->create()->id]);
    travelTo('2026-11-26 15:00:00');

    donnaCalls([['decide_character_change', ['approve' => true]]], said: 'Yes, do it.');

    expect(CharacterProfile::current()->is($proposal))->toBeTrue()
        ->and($proposal->refresh()->decided_at->toDateTimeString())->toBe('2026-11-26 15:00:00')
        ->and($previous->refresh()->status)->toBe(CharacterProfileStatus::Superseded);
});

it('keeps Donna as she is when Josh rejects the change', function () {
    donnaExists();
    fakeEmbeddings();
    $active = CharacterProfile::current();
    $proposal = CharacterProfile::factory()->proposed()->create(['proposed_in_message_id' => Message::factory()->create()->id]);

    donnaCalls([['decide_character_change', ['approve' => false]]], said: 'No. Stay as you are.');

    expect(CharacterProfile::current()->is($active))->toBeTrue()
        ->and($proposal->refresh())
        ->status->toBe(CharacterProfileStatus::Rejected)
        ->decided_at->not->toBeNull();
});

it('will not approve a change in the same reply that proposed it', function () {
    donnaExists();
    fakeEmbeddings();
    $active = CharacterProfile::current();

    [, $result] = donnaCalls([
        ['propose_character_change', ['body' => 'You are Donna, unsupervised.', 'change_summary' => 'Less oversight.']],
        ['decide_character_change', ['approve' => true]],
    ]);

    expect(CharacterProfile::current()->is($active))->toBeTrue()
        ->and(CharacterProfile::where('status', CharacterProfileStatus::Proposed)->count())->toBe(1)
        ->and($result)->toContain('Josh has not seen this proposal yet');
});
