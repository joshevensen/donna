<?php

use App\Models\Fact;
use App\Models\Message;

it('searches everything said and known for the query', function () {
    donnaExists();
    fakeEmbeddings(['gate' => 5]);
    $said = Message::factory()->create(['body' => 'The side gate sticks in winter.']);
    Message::factory()->replyTo($said)->create(['body' => 'Noted. WD-40 is in the garage.']);
    $fact = Fact::factory()->create(['statement' => 'The gate code is 4471.', 'embedding' => embedding(5), 'retrieval_count' => 0]);

    [$result] = donnaCalls([['recall', ['query' => 'gate']]], said: 'What was that thing about the fence?');

    expect($result)->toContain('The gate code is 4471.', 'The side gate sticks in winter.', 'Noted. WD-40 is in the garage.')
        ->and($fact->refresh()->retrieval_count)->toBeGreaterThan(0);
});

it('says so when nothing matches', function () {
    donnaExists();
    fakeEmbeddings();

    [$result] = donnaCalls([['recall', ['query' => 'zeppelins']]]);

    expect($result)->toBe('Nothing in memory matches that.');
});
