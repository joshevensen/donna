<?php

use App\Enums\CostPurpose;
use App\Enums\ExtractionRunStatus;
use App\Enums\FactKind;
use App\Enums\FactProvenance;
use App\Enums\InterviewAreaStatus;
use App\Enums\ProjectStatus;
use App\Extraction\BatchResult;
use App\Jobs\PollExtractions;
use App\Models\Cost;
use App\Models\ExtractionRun;
use App\Models\Fact;
use App\Models\InterviewArea;
use App\Models\Message;
use App\Models\Person;
use App\Models\Project;
use App\Models\Topic;
use Illuminate\Support\Facades\Exceptions;
use Laravel\Ai\Embeddings;

use function Pest\Laravel\travelTo;

/**
 * A complete, empty extraction output, with the given parts filled in.
 *
 * @param  array<string, list<array<string, mixed>>>  $parts
 * @return array<string, list<array<string, mixed>>>
 */
function extracted(array $parts = []): array
{
    return $parts + [
        'facts' => [], 'supersessions' => [], 'people' => [], 'topic_summaries' => [],
        'topic_projects' => [], 'project_candidates' => [], 'moments' => [], 'interview' => [],
    ];
}

/**
 * Finish a submitted run's batch with one day's output and poll for it.
 *
 * @param  array<string, list<array<string, mixed>>>  $output
 */
function applyExtraction(array $output): ExtractionRun
{
    $run = ExtractionRun::factory()->create();

    fakeBatches()->finish($run->batch_id, [new BatchResult('day-2026-11-24', extracted($output), 'claude-sonnet-5-5', 20_000, 2_000)]);

    PollExtractions::dispatchSync();

    return $run->refresh();
}

it('leaves a run alone while its batch is still processing', function () {
    fakeBatches();
    $run = ExtractionRun::factory()->create();

    PollExtractions::dispatchSync();

    expect($run->refresh()->status)->toBe(ExtractionRunStatus::Submitted);
});

it('writes extracted facts about their subjects, with where they came from and how sure they are', function () {
    ['josh' => $josh] = donnaExists();
    fakeEmbeddings(['invoice' => 3]);
    $kristen = Person::factory()->create(['name' => 'Kristen']);
    $message = Message::factory()->create();

    $run = applyExtraction(['facts' => [
        [
            'statement' => 'Kristen prefers to invoice on the 1st.', 'kind' => 'preference', 'confidence' => 0.9,
            'from_interview' => false, 'subjects' => [['type' => 'person', 'name' => 'Kristen']], 'source_message_ids' => [$message->id],
        ],
        [
            'statement' => 'Josh may be thinking about hiring help.', 'kind' => 'fact', 'confidence' => 0.4,
            'from_interview' => true, 'subjects' => [], 'source_message_ids' => [999_999],
        ],
    ]]);

    [$confident, $guess] = Fact::with('people')->oldest('id')->get()->all();

    expect($run)
        ->status->toBe(ExtractionRunStatus::Applied)
        ->results_applied_at->not->toBeNull()
        ->and($confident)
        ->kind->toBe(FactKind::Preference)
        ->provenance->toBe(FactProvenance::Extraction)
        ->confidence->toBe(0.9)
        ->source_message_id->toBe($message->id)
        ->embedding->toBe(embedding(3))
        ->and($confident->people->modelKeys())->toBe([$kristen->id])
        ->and($guess)
        ->provenance->toBe(FactProvenance::Interview)
        ->confidence->toBe(0.4)
        ->source_message_id->toBeNull()
        ->isActive()->toBeTrue()
        ->and($guess->people->modelKeys())->toBe([$josh->id]);
});

it('supersedes a fact the day contradicted and flags the change worth raising', function () {
    donnaExists();
    fakeEmbeddings();
    $old = Fact::factory()->create(['statement' => 'Fibermade pricing is $19 a month.', 'kind' => FactKind::Decision]);

    applyExtraction(['supersessions' => [
        ['fact_id' => $old->id, 'statement' => 'Fibermade pricing is $29 a month.', 'notable_change' => true, 'source_message_ids' => []],
    ]]);

    expect(Fact::query()->active()->sole())
        ->statement->toBe('Fibermade pricing is $29 a month.')
        ->kind->toBe(FactKind::Decision)
        ->notable_change->toBeTrue()
        ->provenance->toBe(FactProvenance::Extraction)
        ->and($old->refresh()->superseded_by_id)->not->toBeNull();
});

it('applies a later day\'s correction to the version an earlier day already wrote', function () {
    donnaExists();
    fakeEmbeddings();
    $original = Fact::factory()->create(['statement' => 'Fibermade pricing is $19 a month.']);
    $run = ExtractionRun::factory()->create();
    $correction = fn (string $statement): array => extracted(['supersessions' => [
        ['fact_id' => $original->id, 'statement' => $statement, 'notable_change' => false, 'source_message_ids' => []],
    ]]);

    // Both days were shown fact #original; the second must land on the first day's replacement.
    fakeBatches()->finish($run->batch_id, [
        new BatchResult('day-2026-11-23', $correction('Fibermade pricing is $25 a month.'), 'claude-sonnet-5-5', 100, 10),
        new BatchResult('day-2026-11-24', $correction('Fibermade pricing is $29 a month.'), 'claude-sonnet-5-5', 100, 10),
    ]);

    PollExtractions::dispatchSync();

    expect(Fact::query()->active()->sole()->statement)->toBe('Fibermade pricing is $29 a month.')
        ->and(Fact::count())->toBe(3);
});

it('meets new people and fills in the ones Donna knows, without touching Josh', function () {
    ['josh' => $josh] = donnaExists();
    fakeEmbeddings();
    $kristen = Person::factory()->create(['name' => 'Kristen', 'relationship' => 'wife', 'summary' => 'Runs Bad Frog.']);

    applyExtraction(['people' => [
        ['name' => 'Kristen', 'aliases' => ['Kris'], 'relationship' => null, 'summary' => 'Runs Bad Frog; doing the Houston show in March.'],
        ['name' => 'Dana', 'aliases' => [], 'relationship' => 'Kristen\'s booth partner', 'summary' => null],
        ['name' => 'Josh', 'aliases' => ['Joshua'], 'relationship' => 'self', 'summary' => 'Overwritten.'],
    ]]);

    expect($kristen->refresh())
        ->aliases->toBe(['Kris'])
        ->relationship->toBe('wife')
        ->summary->toBe('Runs Bad Frog; doing the Houston show in March.')
        ->and(Person::where('name', 'Dana')->sole()->relationship)->toBe('Kristen\'s booth partner')
        ->and($josh->refresh())
        ->aliases->toBe([])
        ->summary->toBeNull();
});

it('refreshes where a topic stands', function () {
    donnaExists();
    fakeEmbeddings(['settled' => 5]);
    $topic = Topic::factory()->create(['name' => 'Fibermade pricing', 'embedding' => embedding(1)]);

    applyExtraction(['topic_summaries' => [['topic_id' => $topic->id, 'summary' => 'Settled on $29 a month.']]]);

    expect($topic->refresh())
        ->summary->toBe('Settled on $29 a month.')
        ->embedding->toBe(embedding(5));
});

it('turns a project candidate into a proposal for Donna to raise, unless the project exists', function () {
    ['josh' => $josh] = donnaExists();
    fakeEmbeddings();
    Project::factory()->create(['name' => 'Fibermade']);

    applyExtraction(['project_candidates' => [
        ['name' => 'Wool Sorter', 'summary' => 'Third week of sketches.'],
        ['name' => 'fibermade', 'summary' => 'Already tracked.'],
    ]]);

    expect(Project::count())->toBe(2)
        ->and(Project::where('name', 'Wool Sorter')->sole())
        ->status->toBe(ProjectStatus::Proposed)
        ->owner_person_id->toBe($josh->id)
        ->summary->toBe('Third week of sketches.');
});

it('files a topic under the project the model says it belongs to', function () {
    donnaExists();
    fakeEmbeddings();
    $topic = Topic::factory()->create(['name' => 'Yarn shows']);
    $project = Project::factory()->create(['name' => 'Fibermade']);

    applyExtraction(['topic_projects' => [
        ['topic_id' => $topic->id, 'project' => 'fibermade'],
        ['topic_id' => $topic->id, 'project' => 'No Such Project'],
    ]]);

    expect($project->topics->modelKeys())->toBe([$topic->id]);
});

it('keeps shared-history moments as facts about Donna', function () {
    ['donna' => $donna] = donnaExists();
    fakeEmbeddings();

    applyExtraction(['moments' => [
        ['statement' => 'Josh and I agreed "the yarn incident" is never to be explained.', 'source_message_ids' => []],
    ]]);

    expect(Fact::sole())
        ->kind->toBe(FactKind::Moment)
        ->and(Fact::sole()->people->modelKeys())->toBe([$donna->id]);
});

it('records interview coverage', function () {
    donnaExists();
    fakeEmbeddings();
    $area = InterviewArea::factory()->create(['area' => 'Work']);

    applyExtraction(['interview' => [['area' => 'work', 'status' => 'covered', 'notes' => 'Fibermade, Bad Frog, the day job.']]]);

    expect($area->refresh())
        ->status->toBe(InterviewAreaStatus::Covered)
        ->notes->toBe('Fibermade, Bad Frog, the day job.');
});

it('records each batch request and the embedding call as costs of the run, at batch prices', function () {
    donnaExists();
    fakeEmbeddings();

    $run = applyExtraction(['moments' => [['statement' => 'A good call.', 'source_message_ids' => []]]]);

    // 20,000 in and 2,000 out on Sonnet 5.5 is $0.06; batched, half that.
    expect(Cost::where('purpose', CostPurpose::Extraction)->sole())
        ->batched->toBeTrue()
        ->usd->toBe('0.030000')
        ->trigger_type->toBe('extraction_run')
        ->trigger_id->toBe($run->id)
        ->and(Cost::where('purpose', CostPurpose::Embedding)->sole()->trigger_id)->toBe($run->id);
});

it('waits and tries again when embeddings are down, without charging the batch twice', function () {
    donnaExists();
    $run = ExtractionRun::factory()->create();
    fakeBatches()->finish($run->batch_id, [
        new BatchResult('day-2026-11-24', extracted(['moments' => [['statement' => 'A good call.', 'source_message_ids' => []]]]), 'claude-sonnet-5-5', 100, 10),
    ]);

    Embeddings::fake(fn () => throw new RuntimeException('Voyage is down.'));

    expect(fn () => PollExtractions::dispatchSync())->toThrow(RuntimeException::class, 'Voyage is down.');
    expect($run->refresh()->status)->toBe(ExtractionRunStatus::Submitted)
        ->and(Fact::count())->toBe(0);

    fakeEmbeddings();
    PollExtractions::dispatchSync();

    expect($run->refresh()->status)->toBe(ExtractionRunStatus::Applied)
        ->and(Fact::sole()->statement)->toBe('A good call.')
        ->and(Cost::where('purpose', CostPurpose::Extraction)->count())->toBe(1);
});

it('applies the days before a failed one and ends the run there, so tomorrow retries the rest', function () {
    Exceptions::fake();
    donnaExists();
    fakeEmbeddings();
    travelTo('2026-11-26 09:00:00');
    $run = ExtractionRun::factory()->create(['window_start' => '2026-11-23 09:00:00', 'window_end' => '2026-11-26 08:59:59']);

    fakeBatches()->finish($run->batch_id, [
        new BatchResult('day-2026-11-25', extracted(['moments' => [['statement' => 'From the 25th.', 'source_message_ids' => []]]]), 'claude-sonnet-5-5', 100, 10),
        new BatchResult('day-2026-11-24', null, error: 'Overloaded'),
        new BatchResult('day-2026-11-23', extracted(['moments' => [['statement' => 'From the 23rd.', 'source_message_ids' => []]]]), 'claude-sonnet-5-5', 100, 10),
    ]);

    PollExtractions::dispatchSync();

    // Midnight on the 24th in Chicago is 06:00 UTC.
    expect(Fact::pluck('statement')->all())->toBe(['From the 23rd.'])
        ->and($run->refresh())
        ->status->toBe(ExtractionRunStatus::Applied)
        ->window_end->toDateTimeString()->toBe('2026-11-24 05:59:59')
        ->and(Cost::where('purpose', CostPurpose::Extraction)->count())->toBe(2);

    Exceptions::assertReported(fn (RuntimeException $exception): bool => str_contains($exception->getMessage(), 'day-2026-11-24'));
});

it('marks the run failed and writes nothing when no day came back', function () {
    Exceptions::fake();
    donnaExists();
    $run = ExtractionRun::factory()->create();

    fakeBatches()->finish($run->batch_id, [new BatchResult('day-2026-11-24', null, error: 'The request expired.')]);

    PollExtractions::dispatchSync();

    expect($run->refresh())
        ->status->toBe(ExtractionRunStatus::Failed)
        ->error->toBe('The request expired.')
        ->and(Fact::count())->toBe(0);
});
