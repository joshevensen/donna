<?php

use App\Enums\ProjectStatus;
use App\Models\Project;

it('saves the suggestion as a proposed project that is not tracked yet', function () {
    ['josh' => $josh] = donnaExists();
    fakeEmbeddings();

    donnaCalls([['propose_project', ['name' => 'Wool Sorter', 'summary' => 'Third week running he\'s sketched this.']]]);

    expect(Project::sole())
        ->name->toBe('Wool Sorter')
        ->status->toBe(ProjectStatus::Proposed)
        ->tier->toBeNull()
        ->owner_person_id->toBe($josh->id)
        ->and(Project::query()->tracked()->exists())->toBeFalse();
});

it('does not propose a project it already has', function () {
    donnaExists();
    fakeEmbeddings();
    Project::factory()->parked()->create(['name' => 'Storyrift']);

    [$result] = donnaCalls([['propose_project', ['name' => 'storyrift']]]);

    expect(Project::count())->toBe(1)
        ->and($result)->toContain('parked');
});
