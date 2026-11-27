<?php

namespace App\Memory;

use App\Memory\Context\Memory;
use App\Memory\Context\RelatedExchange;
use App\Memory\Context\RelatedFact;
use App\Memory\Context\RelatedPerson;
use App\Memory\Context\RelatedTopic;
use Carbon\CarbonInterface;
use Illuminate\Support\Str;

/**
 * Names what a reply's context held, for "What I looked at" in the app.
 * These are what Donna was shown, not proof of what she relied on. Picking
 * and labelling them is code; no model is asked.
 */
class ContextSources
{
    /**
     * The top of the memory block, each as a type and a label a person would
     * recognise. Facts and past messages are ranked by their retrieval score;
     * people and topics have none, so a couple of places are kept for them.
     *
     * @return list<array{type: string, label: string}>
     */
    public function from(Memory $memory): array
    {
        $limit = config('donna.context.sources');

        $named = collect([
            ...$memory->people->map(fn (RelatedPerson $related): array => [
                'type' => 'person',
                'label' => $related->person->name.($related->person->relationship !== null ? " — {$related->person->relationship}" : ''),
            ]),
            ...$memory->topics->map(fn (RelatedTopic $related): array => [
                'type' => 'topic',
                'label' => $related->topic->name,
            ]),
        ]);

        $scored = collect([
            ...$memory->facts->map(fn (RelatedFact $related): array => [
                'score' => $related->score,
                'type' => 'fact',
                'label' => $related->fact->statement.' · '.$this->date($related->fact->created_at, $memory->now),
            ]),
            ...$memory->exchanges->map(fn (RelatedExchange $related): array => [
                'score' => $related->score,
                'type' => 'message',
                'label' => 'You, '.$this->date($related->message->created_at, $memory->now)
                    .' — "'.Str::limit(Str::before(trim($related->message->body), "\n"), 100).'"',
            ]),
        ])
            ->sortByDesc('score')
            ->take($limit - min($named->count(), config('donna.context.sources_named')))
            ->map(fn (array $source): array => ['type' => $source['type'], 'label' => $source['label']]);

        return [...$scored->values(), ...$named->take($limit - $scored->count())];
    }

    /**
     * A date in Josh's timezone, with the year only when it isn't this one.
     */
    private function date(CarbonInterface $moment, CarbonInterface $now): string
    {
        $local = $moment->copy()->timezone(config('donna.timezone'));

        return $local->format($local->year === $now->year ? 'M j' : 'M j, Y');
    }
}
