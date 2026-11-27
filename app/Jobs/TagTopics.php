<?php

namespace App\Jobs;

use App\Ai\Agents\TopicTagger;
use App\Memory\Embedder;
use App\Memory\NameMatcher;
use App\Memory\ProjectLinker;
use App\Models\Message;
use App\Models\Topic;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Tags an exchange with its topics. The model picks the topics; linking
 * messages, creating topics, and the "last discussed" dates are code.
 */
class TagTopics implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /**
     * @var list<int>
     */
    public array $backoff = [30, 300];

    public function __construct(
        public Message $message,
        public Message $reply,
    ) {}

    public function handle(Embedder $embedder, ProjectLinker $links): void
    {
        $candidates = $this->candidates();

        $response = TopicTagger::make(message: $this->message)->prompt($this->prompt($candidates));

        $existing = $candidates->whereIn('id', $response->structured['existing_topic_ids'] ?? []);
        $new = $this->createTopics($response->structured['new_topics'] ?? [], $embedder);

        $topics = $existing->merge($new)->unique('id');

        if ($topics->isEmpty()) {
            return;
        }

        DB::transaction(function () use ($topics, $links): void {
            $this->message->topics()->syncWithoutDetaching($topics->modelKeys());
            $this->reply->topics()->syncWithoutDetaching($topics->modelKeys());

            $at = $this->message->created_at;

            foreach ($topics as $topic) {
                $topic->update([
                    'first_discussed_at' => min($topic->first_discussed_at ?? $at, $at),
                    'last_discussed_at' => max($topic->last_discussed_at ?? $at, $at),
                ]);
            }

            $links->linkTopics($topics);
        });
    }

    /**
     * The existing topics nearest the exchange, for the model to choose from.
     *
     * @return Collection<int, Topic>
     */
    private function candidates(): Collection
    {
        $limit = config('donna.tagging.candidate_topics');

        $nearest = $this->message->embedding === null ? collect() : Topic::query()
            ->whereNotNull('embedding')
            ->orderByVectorDistance('embedding', $this->message->embedding)
            ->limit($limit)
            ->get();

        $named = Topic::query()->orderBy('id')->get()
            ->filter(fn (Topic $topic): bool => NameMatcher::mentions(
                $this->message->body."\n".$this->reply->body, [$topic->name],
            ));

        return $nearest->merge($named)->unique('id')->values();
    }

    /**
     * @param  Collection<int, Topic>  $candidates
     */
    private function prompt(Collection $candidates): string
    {
        $topics = $candidates->isEmpty()
            ? 'None yet.'
            : $candidates->map(fn (Topic $topic): string => "- id {$topic->id}: {$topic->name}"
                .($topic->summary !== null ? " — {$topic->summary}" : ''))->implode("\n");

        return "Existing topics:\n{$topics}\n\nThe exchange:\nJosh: {$this->message->body}\n\nDonna: {$this->reply->body}";
    }

    /**
     * Create the topics the model named that really are new, embedding them
     * in one call. A name that matches an existing topic reuses it. If
     * embeddings are down the topics are saved without a vector and
     * EmbedMissing fills them in later; tagging never waits on Voyage.
     *
     * @param  list<string>  $names
     * @return Collection<int, Topic>
     */
    private function createTopics(array $names, Embedder $embedder): Collection
    {
        $names = collect($names)
            ->map(fn (string $name): string => trim($name))
            ->filter()
            ->unique(fn (string $name): string => mb_strtolower($name))
            ->values();

        if ($names->isEmpty()) {
            return new Collection;
        }

        $known = Topic::query()
            ->whereIn(DB::raw('lower(name)'), $names->map(fn (string $name): string => mb_strtolower($name)))
            ->get();

        $unknown = $names->reject(fn (string $name): bool => $known->contains(
            fn (Topic $topic): bool => NameMatcher::same($topic->name, $name),
        ))->values();

        $embeddings = $embedder->attemptMany($unknown->all(), $this->message);

        return $known->concat($unknown->map(fn (string $name, int $index): Topic => Topic::create([
            'name' => $name,
            'embedding' => $embeddings[$index],
        ])));
    }
}
