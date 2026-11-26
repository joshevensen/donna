<?php

use App\Enums\EvalRating;
use App\Models\EvalExample;
use App\Models\Message;
use App\Models\User;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\postJson;

it('flags one of Donna\'s replies as an example', function () {
    actingAs(User::factory()->create());
    $reply = Message::factory()->fromDonna()->create();

    postJson("/api/v1/messages/{$reply->id}/evaluation", ['rating' => 'bad', 'note' => 'Padded.'])
        ->assertCreated()
        ->assertExactJson(['data' => ['message_id' => $reply->id, 'rating' => 'bad', 'note' => 'Padded.']]);

    expect(EvalExample::sole())
        ->message_id->toBe($reply->id)
        ->rating->toBe(EvalRating::Bad)
        ->note->toBe('Padded.');
});

it('changes the verdict when the same reply is flagged again', function () {
    actingAs(User::factory()->create());
    $example = EvalExample::factory()->create(['rating' => EvalRating::Bad, 'note' => 'Padded.']);

    postJson("/api/v1/messages/{$example->message_id}/evaluation", ['rating' => 'good'])
        ->assertOk()
        ->assertJsonPath('data.rating', 'good')
        ->assertJsonPath('data.note', null);

    expect(EvalExample::sole())
        ->rating->toBe(EvalRating::Good)
        ->note->toBeNull();
});

it('returns 422 when the message is not a finished reply of Donna\'s', function (Closure $message) {
    actingAs(User::factory()->create());

    postJson("/api/v1/messages/{$message()->id}/evaluation", ['rating' => 'good'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['message' => 'Only a reply Donna has finished can be flagged.']);

    expect(EvalExample::count())->toBe(0);
})->with([
    'something Josh said' => [fn () => Message::factory()->create()],
    'a reply still being written' => [fn () => Message::factory()->pending()->create()],
]);
