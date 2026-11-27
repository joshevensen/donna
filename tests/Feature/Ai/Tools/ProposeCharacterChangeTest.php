<?php

use App\Enums\CharacterProfileStatus;
use App\Models\CharacterProfile;
use App\Models\Message;

it('saves the change as a proposed version and leaves Donna as she is', function () {
    donnaExists();
    fakeEmbeddings();
    $active = CharacterProfile::current();

    donnaCalls([['propose_character_change', [
        'body' => 'You are Donna. You have stopped apologising for being right.',
        'change_summary' => 'Drops the hedging about being right.',
    ]]]);

    expect(CharacterProfile::current()->is($active))->toBeTrue()
        ->and(CharacterProfile::where('status', CharacterProfileStatus::Proposed)->sole())
        ->version->toBe(2)
        ->body->toBe('You are Donna. You have stopped apologising for being right.')
        ->change_summary->toBe('Drops the hedging about being right.')
        ->proposed_in_message_id->toBe(Message::first()->id)
        ->decided_at->toBeNull();
});

it('does not stack a second proposal on one Josh has not answered', function () {
    donnaExists();
    fakeEmbeddings();
    CharacterProfile::factory()->proposed()->create();

    [$result] = donnaCalls([['propose_character_change', ['body' => 'You are Donna, again.', 'change_summary' => 'Another idea.']]]);

    expect(CharacterProfile::count())->toBe(2)
        ->and($result)->toContain('still waiting on Josh');
});
