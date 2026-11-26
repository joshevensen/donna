<?php

namespace App\Extraction;

use App\Enums\InterviewAreaStatus;
use App\Enums\PersonKind;
use App\Enums\ProjectStatus;
use App\Memory\HybridSearch;
use App\Memory\MemoryRenderer;
use App\Memory\NameMatcher;
use App\Models\Fact;
use App\Models\InterviewArea;
use App\Models\Message;
use App\Models\Person;
use App\Models\Project;
use App\Models\Topic;
use Illuminate\JsonSchema\JsonSchemaTypeFactory;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Laravel\Ai\Gateway\Anthropic\AnthropicSchemaSanitizer;
use Laravel\Ai\ObjectSchema;

/**
 * Builds the batch requests for a run: one per day of conversation, each
 * carrying the memory that day might touch. Choosing that memory is code.
 */
class ExtractionRequests
{
    public function __construct(
        private MemoryRenderer $renderer,
        private HybridSearch $search,
    ) {}

    /**
     * The custom ID of the request covering one day, e.g. "day-2026-11-25".
     */
    public static function customId(string $date): string
    {
        return "day-{$date}";
    }

    /**
     * @param  Collection<int, Message>  $messages  The window's messages, oldest first.
     * @return list<BatchRequest>
     */
    public function for(Collection $messages): array
    {
        $config = config('donna.models.extraction');
        $system = File::get(resource_path('prompts/extraction.md'));
        $schema = $this->schema();

        return $messages
            ->groupBy(fn (Message $message): string => $this->localDate($message))
            ->map(fn (Collection $day, string $date): BatchRequest => new BatchRequest(
                customId: self::customId($date),
                model: $config['model'],
                system: $system,
                prompt: $this->prompt($date, $day),
                schema: $schema,
                maxTokens: $config['max_tokens'],
                effort: $config['effort'],
            ))
            ->values()
            ->all();
    }

    private function localDate(Message $message): string
    {
        return $message->created_at->copy()->timezone(config('donna.timezone'))->toDateString();
    }

    /**
     * @param  Collection<int, Message>  $day
     */
    private function prompt(string $date, Collection $day): string
    {
        $text = $day->implode('body', "\n");

        $people = Person::query()->where('kind', PersonKind::Other)->orderBy('id')->get();
        $projects = Project::query()->where('status', '!=', ProjectStatus::Dropped)->orderBy('id')->get();
        $topics = Topic::query()
            ->whereHas('messages', fn ($query) => $query->whereKey($day->modelKeys()))
            ->orderBy('id')
            ->get();

        $sections = [
            "Date: {$date} (".config('donna.timezone').')',
            $this->section('People Donna knows', $people->map(fn (Person $person): string => "- {$person->name}"
                .(count($person->aliases) > 0 ? ' (also: '.implode(', ', $person->aliases).')' : '')
                .($person->relationship !== null ? " — {$person->relationship}" : '')
                .($person->summary !== null ? ": {$person->summary}" : ''))),
            $this->section('Josh\'s projects', $projects->map(fn (Project $project): string => "- {$project->name} ("
                .implode(', ', array_filter([$project->tier?->value, $project->status->value])).')')),
            $this->section('Topics discussed this day', $topics->map(fn (Topic $topic): string => "- topic {$topic->id}: {$topic->name}"
                .($topic->summary !== null ? " — {$topic->summary}" : ''))),
            $this->section('Facts Donna already holds', $this->relatedFacts($text, $topics, $people, $projects)
                ->map(fn (Fact $fact): string => $this->renderer->fact($fact))),
            $this->section('Interview areas', InterviewArea::query()->orderBy('id')->get()
                ->map(fn (InterviewArea $area): string => "- {$area->area} ({$area->status->value})"
                    .($area->notes !== null ? ": {$area->notes}" : ''))),
            $this->section('The conversation', $day->map(fn (Message $message): string => "[message {$message->id} | "
                .$message->created_at->copy()->timezone(config('donna.timezone'))->format('g:i A')
                .' | '.ucfirst($message->author->value)."]\n{$message->body}\n")),
        ];

        return implode("\n\n", array_filter($sections));
    }

    /**
     * The active facts the day is most likely to add to or contradict: those
     * about its topics, the projects those belong to, and the people and
     * projects it names; then facts sharing its words; then the newest facts
     * about Josh and Donna, until the request's allowance is full.
     *
     * @param  Collection<int, Topic>  $topics
     * @param  Collection<int, Person>  $people
     * @param  Collection<int, Project>  $projects
     * @return Collection<int, Fact>
     */
    private function relatedFacts(string $text, Collection $topics, Collection $people, Collection $projects): Collection
    {
        $topicIds = $topics->modelKeys();

        $projectIds = [
            ...$projects->filter(fn (Project $project): bool => NameMatcher::mentions($text, $project->names()))->modelKeys(),
            ...DB::table('project_topic')->whereIn('topic_id', $topicIds)->pluck('project_id')->all(),
        ];

        $namedPersonIds = $people->filter(fn (Person $person): bool => NameMatcher::mentions($text, $person->names()))->modelKeys();

        $limit = config('donna.extraction.related_facts_per_request');

        $about = fn (string $type, array $ids): array => Fact::query()
            ->active()
            ->whereIn('id', DB::table('fact_subjects')->select('fact_id')->where('subject_type', $type)->whereIn('subject_id', $ids))
            ->latest('id')
            ->limit($limit)
            ->pluck('id')
            ->all();

        // Most specific first, so a long-held fact about today's topic is never
        // crowded out by whatever Donna learned about Josh most recently.
        $ids = collect([
            ...$about('topic', $topicIds),
            ...$about('project', $projectIds),
            ...$about('person', $namedPersonIds),
            ...array_keys($this->search->facts($text, null)),
            ...$about('person', Person::query()->where('kind', '!=', PersonKind::Other)->pluck('id')->all()),
        ])->unique()->take($limit);

        return Fact::query()
            ->with(['people', 'projects', 'topics', 'predecessors'])
            ->findMany($ids)
            ->sortBy(fn (Fact $fact): int => $ids->search($fact->id))
            ->values();
    }

    /**
     * @param  Collection<int, string>  $lines
     */
    private function section(string $title, Collection $lines): ?string
    {
        return $lines->isEmpty() ? null : "## {$title}\n".$lines->implode("\n");
    }

    /**
     * The structured answer every request must return.
     *
     * @return array<string, mixed>
     */
    private function schema(): array
    {
        $schema = new JsonSchemaTypeFactory;

        $messageIds = fn () => $schema->array()->items($schema->integer())
            ->description('The numbers of the messages this came from.')->required();

        return AnthropicSchemaSanitizer::sanitize((new ObjectSchema([
            'facts' => $schema->array()->items($schema->object([
                'statement' => $schema->string()->required(),
                'kind' => $schema->string()->enum(['fact', 'preference', 'decision', 'opinion'])->required(),
                'confidence' => $schema->number()->description('From 0 to 1.')->required(),
                'from_interview' => $schema->boolean()->required(),
                'subjects' => $schema->array()->items($schema->object([
                    'type' => $schema->string()->enum(['person', 'project', 'topic'])->required(),
                    'name' => $schema->string()->required(),
                ]))->required(),
                'source_message_ids' => $messageIds(),
            ]))->required(),
            'supersessions' => $schema->array()->items($schema->object([
                'fact_id' => $schema->integer()->description('The number of the fact being replaced.')->required(),
                'statement' => $schema->string()->description('The corrected fact.')->required(),
                'notable_change' => $schema->boolean()->required(),
                'source_message_ids' => $messageIds(),
            ]))->required(),
            'people' => $schema->array()->items($schema->object([
                'name' => $schema->string()->required(),
                'aliases' => $schema->array()->items($schema->string())->required(),
                'relationship' => $schema->string()->nullable()->required(),
                'summary' => $schema->string()->nullable()->required(),
            ]))->required(),
            'topic_summaries' => $schema->array()->items($schema->object([
                'topic_id' => $schema->integer()->required(),
                'summary' => $schema->string()->required(),
            ]))->required(),
            'topic_projects' => $schema->array()->items($schema->object([
                'topic_id' => $schema->integer()->required(),
                'project' => $schema->string()->description('The project\'s name, as listed.')->required(),
            ]))->required(),
            'project_candidates' => $schema->array()->items($schema->object([
                'name' => $schema->string()->required(),
                'summary' => $schema->string()->required(),
            ]))->required(),
            'moments' => $schema->array()->items($schema->object([
                'statement' => $schema->string()->required(),
                'source_message_ids' => $messageIds(),
            ]))->required(),
            'interview' => $schema->array()->items($schema->object([
                'area' => $schema->string()->required(),
                'status' => $schema->string()->enum([InterviewAreaStatus::InProgress->value, InterviewAreaStatus::Covered->value])->required(),
                'notes' => $schema->string()->required(),
            ]))->required(),
        ]))->toSchema());
    }
}
