<?php

use App\Enums\InterviewAreaStatus;
use App\Models\InterviewArea;

use function Pest\Laravel\travelTo;

it('records where the interview has got to in an area', function () {
    donnaExists();
    fakeEmbeddings();
    $area = InterviewArea::factory()->create(['area' => 'Life story']);
    travelTo('2026-11-26 15:00:00');

    donnaCalls([['update_interview', [
        'area' => 'life story',
        'status' => 'in_progress',
        'notes' => 'Covered childhood through college. Still open: the Austin years.',
    ]]]);

    expect($area->refresh())
        ->status->toBe(InterviewAreaStatus::InProgress)
        ->notes->toBe('Covered childhood through college. Still open: the Austin years.')
        ->last_touched_at->toDateTimeString()->toBe('2026-11-26 15:00:00');
});

it('names the real areas when asked to update one that does not exist', function () {
    donnaExists();
    fakeEmbeddings();
    InterviewArea::factory()->create(['area' => 'Goals']);

    [$result] = donnaCalls([['update_interview', ['area' => 'Hobbies', 'status' => 'covered']]]);

    expect($result)->toContain('Goals')
        ->and(InterviewArea::sole()->status)->toBe(InterviewAreaStatus::Open);
});
