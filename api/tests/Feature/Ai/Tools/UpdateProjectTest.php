<?php

use App\Enums\ProjectStatus;
use App\Enums\ProjectTier;
use App\Models\Person;
use App\Models\Project;
use App\Models\Topic;

use function Pest\Laravel\travelTo;

it('changes a project\'s status and priority when Josh says so', function () {
    donnaExists();
    fakeEmbeddings();
    $project = Project::factory()->create(['name' => 'Storyrift', 'tier' => ProjectTier::Main, 'status_changed_at' => '2026-01-01 00:00:00']);
    travelTo('2026-11-26 15:00:00');

    donnaCalls([['update_project', ['project' => 'Storyrift', 'status' => 'parked', 'tier' => 'fun']]], said: 'Park Storyrift for now.');

    expect($project->refresh())
        ->status->toBe(ProjectStatus::Parked)
        ->tier->toBe(ProjectTier::Fun)
        ->status_changed_at->toDateTimeString()->toBe('2026-11-26 15:00:00');
});

it('starts tracking a project Donna proposed once Josh agrees', function () {
    donnaExists();
    fakeEmbeddings();
    $project = Project::factory()->proposed()->create(['name' => 'Wool Sorter']);

    donnaCalls([['update_project', ['project' => 'Wool Sorter', 'status' => 'active', 'tier' => 'secondary']]], said: 'Yes, track it.');

    expect(Project::query()->tracked()->sole()->is($project))->toBeTrue();
});

it('starts tracking a project Josh tells her about outright, for whoever owns it, with the topic already named after it', function () {
    donnaExists();
    fakeEmbeddings();
    $kristen = Person::factory()->create(['name' => 'Kristen']);
    $topic = Topic::factory()->create(['name' => 'bad frog']);
    Topic::factory()->create(['name' => 'Frogs']);

    donnaCalls([['update_project', ['project' => 'Bad Frog', 'owner' => 'Kristen', 'tier' => 'main']]], said: 'Bad Frog is Kristen\'s main thing.');

    expect(Project::sole())
        ->name->toBe('Bad Frog')
        ->status->toBe(ProjectStatus::Active)
        ->tier->toBe(ProjectTier::Main)
        ->owner_person_id->toBe($kristen->id)
        ->and(Project::sole()->topics->modelKeys())->toBe([$topic->id]);
});
