<?php

use App\Enums\EvalRating;
use App\Models\EvalExample;
use App\Models\Message;

it('flags the reply before the message, as the verdict and reason Josh gave', function () {
    donnaExists();
    fakeEmbeddings();
    $question = Message::factory()->create();
    $earlier = Message::factory()->replyTo($question)->create();

    [$first] = donnaCalls([['flag_reply', ['rating' => 'bad', 'note' => 'Took his side on Anita.']]]);

    expect(EvalExample::sole())
        ->message_id->toBe($earlier->id)
        ->rating->toBe(EvalRating::Bad)
        ->note->toBe('Took his side on Anita.')
        ->and($first)->toContain("reply #{$earlier->id}");

});

it('says so when there is no earlier reply to flag', function () {
    donnaExists();
    fakeEmbeddings();

    [$result] = donnaCalls([['flag_reply', ['rating' => 'good']]]);

    expect($result)->toContain('no earlier reply')
        ->and(EvalExample::count())->toBe(0);
});
