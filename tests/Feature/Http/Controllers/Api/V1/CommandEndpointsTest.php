<?php

use App\Ai\Agents\Donna;
use App\Ai\Agents\TopicTagger;
use App\Enums\CostPurpose;
use App\Enums\ProjectStatus;
use App\Enums\ProjectTier;
use App\Models\Cost;
use App\Models\Person;
use App\Models\Project;
use App\Models\User;
use Laravel\Ai\Embeddings;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\getJson;
use function Pest\Laravel\travelTo;

/*
 | /projects, /people and /cost in the web app answer from these. They are
 | queries: no model call, no `costs` row (code before LLM).
 */

it('returns 401 without a session', function (string $uri) {
    getJson($uri)->assertUnauthorized();
})->with([
    'projects' => '/api/v1/projects',
    'people' => '/api/v1/people',
    'costs' => '/api/v1/costs',
]);

it('lists the projects that are not dropped, by name, with the owner only when it is not Josh', function () {
    actingAs(User::factory()->create());
    $josh = Person::factory()->josh()->create();
    $kristen = Person::factory()->create(['name' => 'Kristen', 'relationship' => 'wife']);

    $storyrift = Project::factory()->parked()->create(['name' => 'Storyrift', 'tier' => ProjectTier::Fun, 'owner_person_id' => $josh->id]);
    $garage = Project::factory()->proposed()->create(['name' => 'Garage gym', 'owner_person_id' => $kristen->id]);
    $fibermade = Project::factory()->create(['name' => 'fibermade', 'tier' => ProjectTier::Main, 'status' => ProjectStatus::Active]);
    $kitchen = Project::factory()->create(['name' => 'Kitchen remodel', 'tier' => ProjectTier::Secondary, 'owner_person_id' => $kristen->id]);
    Project::factory()->dropped()->create(['name' => 'Abandoned app']);

    getJson('/api/v1/projects')
        ->assertOk()
        ->assertExactJson(['data' => [
            ['id' => $fibermade->id, 'name' => 'fibermade', 'tier' => 'main', 'status' => 'active', 'owner' => null],
            ['id' => $garage->id, 'name' => 'Garage gym', 'tier' => null, 'status' => 'proposed', 'owner' => 'Kristen'],
            ['id' => $kitchen->id, 'name' => 'Kitchen remodel', 'tier' => 'secondary', 'status' => 'active', 'owner' => 'Kristen'],
            ['id' => $storyrift->id, 'name' => 'Storyrift', 'tier' => 'fun', 'status' => 'parked', 'owner' => null],
        ]]);
});

it('lists the people Donna knows, by name, leaving out Josh and Donna herself', function () {
    actingAs(User::factory()->create());
    Person::factory()->josh()->create();
    Person::factory()->donna()->create();
    $sam = Person::factory()->create(['name' => 'sam', 'relationship' => null]);
    $kristen = Person::factory()->create(['name' => 'Kristen', 'relationship' => 'wife']);
    $alex = Person::factory()->create(['name' => 'Alex', 'relationship' => 'friend']);

    getJson('/api/v1/people')
        ->assertOk()
        ->assertExactJson(['data' => [
            ['id' => $alex->id, 'name' => 'Alex', 'relationship' => 'friend'],
            ['id' => $kristen->id, 'name' => 'Kristen', 'relationship' => 'wife'],
            ['id' => $sam->id, 'name' => 'sam', 'relationship' => null],
        ]]);
});

// The periods themselves are CostReport's (tests/Feature/Ai/Tools/CostReportTest.php); this is the endpoint's shape.
it('reports spend today and this month, by purpose', function () {
    actingAs(User::factory()->create());
    // 9am on Nov 26 in Chicago.
    travelTo('2026-11-26 15:00:00');
    Cost::factory()->create(['purpose' => CostPurpose::Reply, 'usd' => '1.250000', 'created_at' => '2026-11-26 12:00:00']);
    Cost::factory()->create(['purpose' => CostPurpose::Embedding, 'usd' => '0.010000', 'created_at' => '2026-11-26 14:00:00']);
    Cost::factory()->create(['purpose' => CostPurpose::Extraction, 'usd' => '0.250000', 'created_at' => '2026-11-10 12:00:00']);

    getJson('/api/v1/costs')
        ->assertOk()
        ->assertExactJson(['data' => [
            'today' => [
                'period' => 'today',
                'from' => '2026-11-26',
                'until' => '2026-11-26',
                'total_usd' => '1.26',
                'calls' => 2,
                'web_searches' => 0,
                'by_purpose' => [
                    'embedding' => ['usd' => '0.01', 'calls' => 1],
                    'reply' => ['usd' => '1.25', 'calls' => 1],
                ],
            ],
            'this_month' => [
                'period' => 'this_month',
                'from' => '2026-11-01',
                'until' => '2026-11-30',
                'total_usd' => '1.51',
                'calls' => 3,
                'web_searches' => 0,
                'by_purpose' => [
                    'embedding' => ['usd' => '0.01', 'calls' => 1],
                    'extraction' => ['usd' => '0.25', 'calls' => 1],
                    'reply' => ['usd' => '1.25', 'calls' => 1],
                ],
            ],
        ]]);
});

it('answers without calling a model or recording a cost', function (string $uri) {
    actingAs(User::factory()->create());
    Person::factory()->josh()->create();
    Person::factory()->donna()->create();
    Person::factory()->create(['name' => 'Kristen']);
    Project::factory()->create(['name' => 'Fibermade']);
    Cost::factory()->create();

    Donna::fake()->preventStrayPrompts();
    TopicTagger::fake()->preventStrayPrompts();
    Embeddings::fake()->preventStrayEmbeddings();

    getJson($uri)->assertOk();

    Donna::assertNeverPrompted();
    TopicTagger::assertNeverPrompted();
    Embeddings::assertNothingGenerated();
    expect(Cost::count())->toBe(1);
})->with([
    'projects' => '/api/v1/projects',
    'people' => '/api/v1/people',
    'costs' => '/api/v1/costs',
]);
