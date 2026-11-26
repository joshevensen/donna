<?php

namespace App\Extraction;

use App\Enums\FactKind;
use App\Enums\FactProvenance;
use App\Enums\InterviewAreaStatus;
use App\Enums\PersonKind;
use App\Enums\ProjectStatus;
use App\Memory\Embedder;
use App\Memory\FactWriter;
use App\Memory\NameMatcher;
use App\Memory\ProjectLinker;
use App\Memory\SubjectResolver;
use App\Models\ExtractionRun;
use App\Models\Fact;
use App\Models\InterviewArea;
use App\Models\Message;
use App\Models\Person;
use App\Models\Project;
use App\Models\Topic;
use Illuminate\Support\Collection;

/**
 * Writes what the model extracted from one day into memory. The model made
 * the judgment calls; everything here is deterministic.
 */
class ExtractionApplier
{
    public function __construct(
        private Embedder $embedder,
        private FactWriter $facts,
        private SubjectResolver $subjects,
        private ProjectLinker $links,
    ) {}

    /**
     * Make every model call one day's output needs, before any of it is
     * written: embed what it adds, and meet the topics it names. Kept apart
     * from apply() so that no call, or its cost, sits inside the transaction.
     *
     * @param  array<string, mixed>  $output
     * @return Collection<int, list<float>> Embeddings in the order apply() uses them.
     */
    public function prepare(ExtractionRun $run, array $output): Collection
    {
        foreach ($output['facts'] ?? [] as $fact) {
            foreach ($fact['subjects'] ?? [] as $subject) {
                if ($subject['type'] === 'topic') {
                    $this->subjects->topic($subject['name'], create: true, trigger: $run);
                }
            }
        }

        return $this->embed($run, $output);
    }

    /**
     * Apply one day's structured output.
     *
     * @param  array<string, mixed>  $output
     * @param  Collection<int, list<float>>  $embeddings  From prepare().
     */
    public function apply(ExtractionRun $run, array $output, Collection $embeddings): void
    {
        $messages = Message::query()
            ->whereIn('id', $this->sourceIds($output))
            ->where('created_at', '<=', $run->window_end)
            ->get()
            ->keyBy('id');

        foreach ($output['people'] ?? [] as $person) {
            $this->savePerson($person);
        }

        foreach ($output['supersessions'] ?? [] as $supersession) {
            $old = $this->currentVersion($supersession['fact_id']);

            if ($old !== null) {
                $this->facts->supersede(
                    old: $old,
                    statement: $supersession['statement'],
                    provenance: FactProvenance::Extraction,
                    notableChange: (bool) $supersession['notable_change'],
                    source: $this->source($messages, $supersession),
                    embedding: $embeddings->shift(),
                );
            } else {
                $embeddings->shift();
            }
        }

        foreach ($output['facts'] ?? [] as $fact) {
            [$subjects] = $this->subjects->resolve($fact['subjects'] ?? [], $run);

            $this->facts->write(
                statement: $fact['statement'],
                kind: FactKind::tryFrom($fact['kind']) ?? FactKind::Fact,
                provenance: $fact['from_interview'] ? FactProvenance::Interview : FactProvenance::Extraction,
                subjects: $subjects === [] ? [Person::josh()] : $subjects,
                source: $this->source($messages, $fact),
                confidence: max(0.0, min(1.0, (float) $fact['confidence'])),
                embedding: $embeddings->shift(),
            );
        }

        foreach ($output['moments'] ?? [] as $moment) {
            $this->facts->write(
                statement: $moment['statement'],
                kind: FactKind::Moment,
                provenance: FactProvenance::Extraction,
                subjects: [Person::donna()],
                source: $this->source($messages, $moment),
                embedding: $embeddings->shift(),
            );
        }

        foreach ($output['topic_summaries'] ?? [] as $summary) {
            $embedding = $embeddings->shift();

            Topic::query()->find($summary['topic_id'])?->update([
                'summary' => $summary['summary'],
                'embedding' => $embedding,
            ]);
        }

        foreach ($output['project_candidates'] ?? [] as $candidate) {
            if ($this->subjects->project($candidate['name']) === null) {
                $this->links->linkProject(Project::create([
                    'name' => trim($candidate['name']),
                    'summary' => $candidate['summary'],
                    'status' => ProjectStatus::Proposed,
                    'owner_person_id' => Person::josh()->id,
                    'status_changed_at' => now(),
                ]));
            }
        }

        foreach ($output['topic_projects'] ?? [] as $pair) {
            $topic = Topic::query()->find($pair['topic_id']);
            $project = $this->subjects->project($pair['project']);

            if ($topic !== null && $project !== null) {
                $this->links->link($topic, $project);
            }
        }

        foreach ($output['interview'] ?? [] as $progress) {
            InterviewArea::query()->get()
                ->first(fn (InterviewArea $area): bool => NameMatcher::same($area->area, $progress['area']))
                ?->update([
                    'status' => InterviewAreaStatus::tryFrom($progress['status']) ?? InterviewAreaStatus::InProgress,
                    'notes' => $progress['notes'],
                    'last_touched_at' => now(),
                ]);
        }
    }

    /**
     * The active fact at the head of a fact's supersession chain. An earlier
     * day in the same run may already have replaced the fact the model saw.
     */
    private function currentVersion(int $id): ?Fact
    {
        $fact = Fact::query()->find($id);

        for ($hops = 0; $fact !== null && ! $fact->isActive() && $hops < 50; $hops++) {
            $fact = $fact->superseded_by_id === null ? null : Fact::query()->find($fact->superseded_by_id);
        }

        return $fact?->isActive() ? $fact : null;
    }

    /**
     * Embed everything the day adds in a single call, in the order it is
     * applied: supersessions, facts, moments, then topic summaries.
     *
     * @param  array<string, mixed>  $output
     * @return Collection<int, list<float>>
     */
    private function embed(ExtractionRun $run, array $output): Collection
    {
        $topics = Topic::query()->findMany(array_column($output['topic_summaries'] ?? [], 'topic_id'))->keyBy('id');

        $texts = [
            ...array_column($output['supersessions'] ?? [], 'statement'),
            ...array_column($output['facts'] ?? [], 'statement'),
            ...array_column($output['moments'] ?? [], 'statement'),
            ...array_map(
                fn (array $summary): string => ($topics[$summary['topic_id']]->name ?? 'Topic').': '.$summary['summary'],
                $output['topic_summaries'] ?? [],
            ),
        ];

        return collect($this->embedder->embedMany($texts, $run));
    }

    /**
     * Add someone new, or fill in what the day added about someone known.
     * Josh and Donna are never renamed or re-described from here.
     *
     * @param  array{name: string, aliases?: list<string>, relationship?: string|null, summary?: string|null}  $input
     */
    private function savePerson(array $input): void
    {
        $person = $this->subjects->person($input['name']) ?? new Person(['name' => trim($input['name'])]);

        if ($person->kind !== PersonKind::Other) {
            return;
        }

        $person->fill(array_filter([
            'relationship' => $input['relationship'] ?? null,
            'summary' => $input['summary'] ?? null,
        ], filled(...)));

        $person->aliases = collect([...$person->aliases, ...($input['aliases'] ?? [])])
            ->map(fn (string $alias): string => trim($alias))
            ->reject(fn (string $alias): bool => $alias === '' || NameMatcher::same($alias, $person->name))
            ->unique()
            ->values()
            ->all();

        $person->save();
    }

    /**
     * Every message number the output cites.
     *
     * @param  array<string, mixed>  $output
     * @return list<int>
     */
    private function sourceIds(array $output): array
    {
        return collect(['facts', 'supersessions', 'moments'])
            ->flatMap(fn (string $key): array => array_merge(...array_column($output[$key] ?? [], 'source_message_ids'), ...[[]]))
            ->unique()
            ->values()
            ->all();
    }

    /**
     * The first cited message that really exists in the run's window.
     *
     * @param  Collection<int, Message>  $messages
     * @param  array<string, mixed>  $item
     */
    private function source(Collection $messages, array $item): ?Message
    {
        foreach ($item['source_message_ids'] ?? [] as $id) {
            if ($messages->has($id)) {
                return $messages[$id];
            }
        }

        return null;
    }
}
