<?php

use App\Enums\CostPurpose;
use App\Jobs\EmbedMessage;
use App\Models\Cost;
use App\Models\Message;

it('embeds Donna\'s reply so it can be found by meaning, charging the exchange', function () {
    fakeEmbeddings(['supplier' => 3]);
    $message = Message::factory()->create();
    $reply = Message::factory()->replyTo($message)->create(['body' => 'Then we find another supplier.']);

    EmbedMessage::dispatchSync($reply);

    expect($reply->refresh()->embedding)->toBe(embedding(3))
        ->and(Cost::sole())
        ->purpose->toBe(CostPurpose::Embedding)
        ->model->toBe('voyage-4')
        ->trigger_id->toBe($message->id);
});
