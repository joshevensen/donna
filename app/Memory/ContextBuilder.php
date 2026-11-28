<?php

namespace App\Memory;

use App\Enums\CharacterProfileStatus;
use App\Enums\FileRole;
use App\Enums\InterviewAreaStatus;
use App\Enums\MessageAuthor;
use App\Enums\PersonKind;
use App\Enums\ProjectStatus;
use App\Files\FileLibrary;
use App\Memory\Context\AttachedFile;
use App\Memory\Context\FileInPlay;
use App\Memory\Context\Memory;
use App\Memory\Context\Mentions;
use App\Memory\Context\RelatedChunk;
use App\Memory\Context\RelatedExchange;
use App\Memory\Context\RelatedFact;
use App\Memory\Context\RelatedPerson;
use App\Memory\Context\RelatedTopic;
use App\Memory\Context\ReplyContext;
use App\Models\CharacterProfile;
use App\Models\File;
use App\Models\InterviewArea;
use App\Models\Message;
use App\Models\Person;
use App\Models\Project;
use App\Models\Topic;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File as Filesystem;
use Laravel\Ai\Files\File as Attachment;
use Laravel\Ai\Messages\Message as Turn;

/**
 * Rebuilds Donna's context from memory for one message. All of it is code:
 * the only model call behind a context is the embedding of Josh's message,
 * which is already on the row by the time this runs.
 */
class ContextBuilder
{
    public function __construct(
        private HybridSearch $search,
        private Recollection $recollection,
        private MemoryRenderer $renderer,
        private MentionResolver $mentions,
        private ContextSources $sources,
        private FileContext $files,
        private FileLibrary $library,
    ) {}

    public function build(Message $message): ReplyContext
    {
        $recent = $this->recentMessages($message);

        $attached = $this->files->attached($message);

        $memory = $this->fitToBudget($this->recall($message, $recent));

        $this->recollection->markRetrieved($memory->facts);

        return new ReplyContext(
            character: $this->character(),
            profile: $this->profile(),
            turns: $this->turns($recent),
            // His memory, then anything he attached, then what he said.
            prompt: implode("\n\n", array_filter([
                $this->renderer->render($memory),
                $this->renderer->attached($attached),
                filled($message->body) ? $message->body : '(Josh sent this with no message.)',
            ])),
            memory: $memory,
            sources: $this->sources->from($memory),
            attachments: $attached->map(fn (AttachedFile $file): ?Attachment => $file->attachment)->filter()->values()->all(),
        );
    }

    /**
     * The last stretch of conversation, verbatim: Donna's working memory.
     *
     * @return Collection<int, Message>
     */
    private function recentMessages(Message $message): Collection
    {
        $recent = Message::query()
            ->complete()
            ->where('id', '<', $message->id)
            ->latest('id')
            ->limit(config('donna.context.recent_messages'))
            ->get()
            ->reverse()
            ->values();

        $budget = config('donna.context.recent_token_budget');

        while ($recent->isNotEmpty() && Tokens::estimate($recent->implode('body', "\n")) > $budget) {
            $recent->shift();
        }

        // A conversation has to open with Josh speaking.
        while ($recent->isNotEmpty() && $recent->first()->isFromDonna()) {
            $recent->shift();
        }

        return $recent->values();
    }

    /**
     * @param  Collection<int, Message>  $recent
     * @return list<Turn>
     */
    private function turns(Collection $recent): array
    {
        $recent->loadMissing('files');

        return $recent->map(fn (Message $message): Turn => $message->isFromDonna()
            ? new Turn('assistant', $message->body)
            : new Turn('user', implode("\n", array_filter([
                '['.$this->renderer->timestamp($message->created_at).']',
                $this->attachedNote($message),
                $message->body,
            ], filled(...))))
        )->all();
    }

    /**
     * A past message's attachments, named, so "the second one" has something to point at.
     */
    private function attachedNote(Message $message): ?string
    {
        $files = $message->files->filter(fn (File $file): bool => $file->pivot->role === FileRole::Attached->value);

        return $files->isEmpty() ? null : '[attached: '.$files
            ->map(fn (File $file): string => "{$file->name} (file #{$file->id})")
            ->implode(', ').']';
    }

    /**
     * @param  Collection<int, Message>  $recent
     */
    private function recall(Message $message, Collection $recent): Memory
    {
        $embedding = $message->embedding;

        $topics = $this->relatedTopics($message, $embedding);
        $projects = $this->trackedProjects();
        $relatedProjectIds = $this->relatedProjectIds($message, $topics, $projects);

        $facts = $this->relatedFacts($message, $embedding, $relatedProjectIds);

        $everyone = Person::query()->orderBy('id')->get();
        $mentions = $this->mentions->resolve($message->body, $everyone);

        $filesInPlay = $this->files->inPlay($message);

        return new Memory(
            now: CarbonImmutable::now(config('donna.timezone')),
            previousMessageAt: $this->previousMessageAt($message),
            exchanges: $this->relatedExchanges($message, $embedding, $recent, $relatedProjectIds),
            facts: $facts,
            people: $this->relatedPeople($message, $facts, $everyone, $mentions),
            topics: $topics,
            projects: $projects,
            relatedProjectIds: $relatedProjectIds,
            interviewAreas: InterviewArea::query()
                ->where('status', '!=', InterviewAreaStatus::Covered)
                ->orderBy('id')
                ->get(),
            proposedProfiles: CharacterProfile::query()
                ->where('status', CharacterProfileStatus::Proposed)
                ->orderBy('version')
                ->get(),
            proposedProjects: Project::query()
                ->where('status', ProjectStatus::Proposed)
                ->orderBy('id')
                ->get(),
            mentions: $mentions,
            chunks: $this->relatedChunks($message, $embedding, $filesInPlay, $relatedProjectIds, $topics, $everyone, $mentions),
            files: $filesInPlay,
            fileCounts: $this->library->counts(),
        );
    }

    /**
     * The best-matching parts of kept files: Archive Memory. Parts of a file
     * already in play are left out, since she has that file to hand. A file
     * linked to a project, person or topic this message relates to gets the
     * same fixed boost a project gives its facts.
     *
     * @param  list<float>|null  $embedding
     * @param  Collection<int, FileInPlay>  $filesInPlay
     * @param  Collection<int, int>  $relatedProjectIds
     * @param  Collection<int, RelatedTopic>  $topics
     * @param  Collection<int, Person>  $everyone
     * @return Collection<int, RelatedChunk>
     */
    private function relatedChunks(
        Message $message,
        ?array $embedding,
        Collection $filesInPlay,
        Collection $relatedProjectIds,
        Collection $topics,
        Collection $everyone,
        Mentions $mentions,
    ): Collection {
        $scores = $this->search->chunks(
            $message->body,
            $embedding,
            exceptFileIds: $filesInPlay->map(fn (FileInPlay $inPlay): int => $inPlay->file->id)->all(),
        );

        if ($scores === []) {
            return new Collection;
        }

        $topicIds = $topics->map(fn (RelatedTopic $related): int => $related->topic->id);

        $personIds = $everyone
            ->filter(fn (Person $person): bool => $mentions->people()->contains('id', $person->id)
                || NameMatcher::mentions($message->body, $person->names()))
            ->modelKeys();

        $boosted = DB::table('file_chunks')
            ->join('file_subjects', 'file_subjects.file_id', '=', 'file_chunks.file_id')
            ->whereIn('file_chunks.id', array_keys($scores))
            ->where(fn ($query) => $query
                ->where(fn ($query) => $query->where('subject_type', 'project')->whereIn('subject_id', $relatedProjectIds))
                ->orWhere(fn ($query) => $query->where('subject_type', 'person')->whereIn('subject_id', $personIds))
                ->orWhere(fn ($query) => $query->where('subject_type', 'topic')->where(fn ($query) => $query
                    ->whereIn('subject_id', $topicIds)
                    ->orWhereIn('subject_id', DB::table('project_topic')->whereIn('project_id', $relatedProjectIds)->select('topic_id')))))
            ->distinct()
            ->pluck('file_chunks.id')
            ->all();

        return $this->recollection->chunks($this->boost($scores, $boosted), config('donna.context.related_chunks'));
    }

    /**
     * Past exchanges outside the recent window.
     *
     * @param  list<float>|null  $embedding
     * @param  Collection<int, Message>  $recent
     * @param  Collection<int, int>  $relatedProjectIds
     * @return Collection<int, RelatedExchange>
     */
    private function relatedExchanges(Message $message, ?array $embedding, Collection $recent, Collection $relatedProjectIds): Collection
    {
        $scores = $this->search->messages(
            $message->body,
            $embedding,
            exceptIds: [...$recent->modelKeys(), $message->id],
        );

        $boosted = $relatedProjectIds->isEmpty() ? [] : DB::table('message_topic')
            ->join('project_topic', 'project_topic.topic_id', '=', 'message_topic.topic_id')
            ->whereIn('message_topic.message_id', array_keys($scores))
            ->whereIn('project_topic.project_id', $relatedProjectIds)
            ->distinct()
            ->pluck('message_topic.message_id')
            ->all();

        return $this->recollection->exchanges(
            $this->boost($scores, $boosted),
            config('donna.context.related_messages'),
        );
    }

    /**
     * Active facts related to the message.
     *
     * @param  list<float>|null  $embedding
     * @param  Collection<int, int>  $relatedProjectIds
     * @return Collection<int, RelatedFact>
     */
    private function relatedFacts(Message $message, ?array $embedding, Collection $relatedProjectIds): Collection
    {
        $scores = $this->search->facts($message->body, $embedding);

        $boosted = $relatedProjectIds->isEmpty() ? [] : DB::table('fact_subjects')
            ->whereIn('fact_id', array_keys($scores))
            ->where(function ($query) use ($relatedProjectIds): void {
                $query
                    ->where(fn ($query) => $query
                        ->where('subject_type', 'project')
                        ->whereIn('subject_id', $relatedProjectIds))
                    ->orWhere(fn ($query) => $query
                        ->where('subject_type', 'topic')
                        ->whereIn('subject_id', DB::table('project_topic')
                            ->whereIn('project_id', $relatedProjectIds)
                            ->select('topic_id')));
            })
            ->distinct()
            ->pluck('fact_id')
            ->all();

        return $this->recollection->facts(
            $this->boost($scores, $boosted),
            config('donna.context.related_facts'),
        );
    }

    /**
     * Anyone named in the message, by plain string match or with `@`, or who
     * a retrieved fact is about. Josh and Donna are always in context already.
     *
     * @param  Collection<int, RelatedFact>  $facts
     * @param  Collection<int, Person>  $everyone
     * @return Collection<int, RelatedPerson>
     */
    private function relatedPeople(Message $message, Collection $facts, Collection $everyone, Mentions $mentions): Collection
    {
        $aboutIds = $facts->flatMap(fn (RelatedFact $related) => $related->fact->people->modelKeys())->unique();
        $mentionedIds = $mentions->people()->pluck('id')->all();
        $shownFactIds = $facts->map(fn (RelatedFact $related): int => $related->fact->id)->all();

        return $everyone
            ->where('kind', PersonKind::Other)
            ->filter(fn (Person $person): bool => $aboutIds->contains($person->id)
                || in_array($person->id, $mentionedIds, true)
                || NameMatcher::mentions($message->body, $person->names()))
            ->map(fn (Person $person): RelatedPerson => new RelatedPerson(
                person: $person,
                facts: $person->facts()
                    ->with(['people', 'projects', 'topics', 'predecessors'])
                    ->active()
                    ->whereKeyNot($shownFactIds)
                    ->orderByDesc('retrieval_count')
                    ->latest('facts.id')
                    ->limit(config('donna.context.people_top_facts'))
                    ->get(),
            ))
            ->values();
    }

    /**
     * Topics the message is about: named outright, or close by embedding.
     *
     * @param  list<float>|null  $embedding
     * @return Collection<int, RelatedTopic>
     */
    private function relatedTopics(Message $message, ?array $embedding): Collection
    {
        $named = Topic::query()
            ->get(['id', 'name'])
            ->filter(fn (Topic $topic): bool => NameMatcher::mentions($message->body, [$topic->name]))
            ->modelKeys();

        $near = $embedding === null ? [] : Topic::query()
            ->whereNotNull('embedding')
            ->whereVectorDistanceLessThan('embedding', $embedding, config('donna.context.topic_max_distance'))
            ->orderByVectorDistance('embedding', $embedding)
            ->limit(config('donna.context.nearest_topics'))
            ->pluck('id')
            ->all();

        $topics = Topic::query()
            ->with('projects')
            ->findMany(array_unique([...$named, ...$near]))
            ->sortByDesc('last_discussed_at')
            ->values();

        $mentions = $this->mentionCounts($topics->modelKeys());
        $now = now();

        return $topics->map(function (Topic $topic) use ($mentions, $now): RelatedTopic {
            $daysIdle = $topic->last_discussed_at === null
                ? null
                : (int) $topic->last_discussed_at->diffInDays($now);

            return new RelatedTopic(
                topic: $topic,
                daysIdle: $daysIdle,
                stale: $daysIdle !== null && $daysIdle >= config('donna.context.stale_topic_days'),
                mentions: $mentions[$topic->id] ?? $this->noMentions(),
            );
        });
    }

    /**
     * How often Josh has brought each topic up, per window: his messages and
     * the distinct days (in his timezone) he did. Counted in SQL.
     *
     * @param  list<int>  $topicIds
     * @return array<int, array<int, array{messages: int, days: int}>>
     */
    private function mentionCounts(array $topicIds): array
    {
        if ($topicIds === []) {
            return [];
        }

        $windows = config('donna.context.mention_windows');
        $localDay = "(messages.created_at at time zone 'UTC' at time zone ?)::date";

        $query = DB::table('message_topic')
            ->join('messages', 'messages.id', '=', 'message_topic.message_id')
            ->whereIn('message_topic.topic_id', $topicIds)
            ->where('messages.author', MessageAuthor::Josh->value)
            ->where('messages.created_at', '>=', now()->subDays(max($windows)))
            ->groupBy('message_topic.topic_id')
            ->select('message_topic.topic_id');

        foreach ($windows as $days) {
            $since = now()->subDays($days);

            $query
                ->selectRaw("count(*) filter (where messages.created_at >= ?) as messages_{$days}", [$since])
                ->selectRaw("count(distinct {$localDay}) filter (where messages.created_at >= ?) as days_{$days}", [config('donna.timezone'), $since]);
        }

        return $query->get()->mapWithKeys(fn (object $row): array => [
            $row->topic_id => collect($windows)->mapWithKeys(fn (int $days): array => [
                $days => ['messages' => (int) $row->{"messages_{$days}"}, 'days' => (int) $row->{"days_{$days}"}],
            ])->all(),
        ])->all();
    }

    /**
     * @return array<int, array{messages: int, days: int}>
     */
    private function noMentions(): array
    {
        return collect(config('donna.context.mention_windows'))
            ->mapWithKeys(fn (int $days): array => [$days => ['messages' => 0, 'days' => 0]])
            ->all();
    }

    /**
     * Active and parked projects with when each was last talked about,
     * so Donna can say what has gone quiet.
     *
     * @return Collection<int, Project>
     */
    private function trackedProjects(): Collection
    {
        return Project::query()
            ->tracked()
            ->with('owner')
            ->addSelect(['last_discussed_at' => Topic::query()
                ->selectRaw('max(topics.last_discussed_at)')
                ->join('project_topic', 'project_topic.topic_id', '=', 'topics.id')
                ->whereColumn('project_topic.project_id', 'projects.id'),
            ])
            ->withCasts(['last_discussed_at' => 'datetime'])
            ->orderBy('id')
            ->get();
    }

    /**
     * The projects this message relates to: named in it, or linked to one of
     * its topics. Their memories get a boost; nothing else is ever hidden.
     *
     * @param  Collection<int, RelatedTopic>  $topics
     * @param  Collection<int, Project>  $projects
     * @return Collection<int, int>
     */
    private function relatedProjectIds(Message $message, Collection $topics, Collection $projects): Collection
    {
        $named = $projects
            ->filter(fn (Project $project): bool => NameMatcher::mentions($message->body, $project->names()))
            ->modelKeys();

        $viaTopics = $topics
            ->flatMap(fn (RelatedTopic $related) => $related->topic->projects->modelKeys())
            ->intersect($projects->modelKeys())
            ->all();

        return collect([...$named, ...$viaTopics])->unique()->values();
    }

    /**
     * Add the fixed project bonus to the given IDs and re-rank.
     *
     * @param  array<int, float>  $scores
     * @param  list<int>  $boostedIds
     * @return array<int, float>
     */
    private function boost(array $scores, array $boostedIds): array
    {
        foreach ($boostedIds as $id) {
            $scores[$id] += config('donna.context.project_boost');
        }

        arsort($scores);

        return $scores;
    }

    private function previousMessageAt(Message $message): ?CarbonImmutable
    {
        $previous = Message::query()
            ->where('author', MessageAuthor::Josh)
            ->where('id', '<', $message->id)
            ->latest('id')
            ->value('created_at');

        return $previous?->toImmutable();
    }

    /**
     * Keep the memory block within budget by dropping the lowest-scoring
     * retrieved exchanges, facts and file chunks, one at a time.
     */
    private function fitToBudget(Memory $memory): Memory
    {
        $budget = config('donna.context.memory_token_budget');

        while (Tokens::estimate($this->renderer->render($memory)) > $budget) {
            // The weakest of each kind is last; whichever of those scores lowest goes.
            $weakest = collect([$memory->exchanges, $memory->facts, $memory->chunks])
                ->filter(fn (Collection $retrieved): bool => $retrieved->isNotEmpty())
                ->sortBy(fn (Collection $retrieved): float => $retrieved->last()->score)
                ->first();

            if ($weakest === null) {
                break;
            }

            $weakest->pop();
        }

        return $memory;
    }

    /**
     * Who Donna is and how she works. Changes only when her profile does.
     */
    private function character(): string
    {
        return CharacterProfile::current()->body
            ."\n\n".Filesystem::get(resource_path('prompts/operating-instructions.md'));
    }

    /**
     * Who Josh is and what he is working on. Changes rarely.
     */
    private function profile(): string
    {
        return $this->renderer->profile(
            Person::josh(),
            Project::query()->tracked()->with('owner')->orderBy('id')->get(),
        );
    }
}
