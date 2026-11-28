<?php

namespace App\Memory;

use App\Enums\MessageStatus;
use App\Memory\Context\RelatedChunk;
use App\Memory\Context\RelatedExchange;
use App\Memory\Context\RelatedFact;
use App\Models\Fact;
use App\Models\FileChunk;
use App\Models\Message;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Turns search scores into the memories themselves, loaded with everything
 * needed to show them.
 */
class Recollection
{
    /**
     * Each hit as Josh's message with the reply it got, whichever half the
     * search actually matched.
     *
     * @param  array<int, float>  $scores  Keyed by message ID.
     * @return Collection<int, RelatedExchange>
     */
    public function exchanges(array $scores, int $limit): Collection
    {
        return Message::query()
            ->with(['reply', 'inReplyTo'])
            ->findMany(array_keys($scores))
            ->map(function (Message $hit) use ($scores): RelatedExchange {
                $said = $hit->isFromDonna() && $hit->inReplyTo !== null ? $hit->inReplyTo : $hit;
                $reply = $said->is($hit) ? $hit->reply : $hit;

                return new RelatedExchange(
                    message: $said,
                    reply: $reply?->status === MessageStatus::Complete && ! $reply->is($said) ? $reply : null,
                    score: $scores[$hit->id],
                );
            })
            ->sortByDesc('score')
            ->unique(fn (RelatedExchange $exchange): int => $exchange->message->id)
            ->take($limit)
            ->values();
    }

    /**
     * Each fact with its subjects and the superseded facts it replaced, so
     * Donna can see what Josh used to think.
     *
     * @param  array<int, float>  $scores  Keyed by fact ID.
     * @return Collection<int, RelatedFact>
     */
    public function facts(array $scores, int $limit): Collection
    {
        $scores = array_slice($scores, 0, $limit, preserve_keys: true);

        return Fact::query()
            ->with(['people', 'projects', 'topics', 'predecessors'])
            ->findMany(array_keys($scores))
            ->map(fn (Fact $fact): RelatedFact => new RelatedFact($fact, $scores[$fact->id]))
            ->sortByDesc('score')
            ->values();
    }

    /**
     * Each chunk with the file it is part of.
     *
     * @param  array<int, float>  $scores  Keyed by chunk ID.
     * @return Collection<int, RelatedChunk>
     */
    public function chunks(array $scores, int $limit): Collection
    {
        $scores = array_slice($scores, 0, $limit, preserve_keys: true);

        return FileChunk::query()
            ->with('file')
            ->findMany(array_keys($scores))
            ->map(fn (FileChunk $chunk): RelatedChunk => new RelatedChunk($chunk, $scores[$chunk->id]))
            ->sortByDesc('score')
            ->values();
    }

    /**
     * Note which facts were actually put in front of Donna, for curation later.
     *
     * @param  Collection<int, RelatedFact>  $facts
     */
    public function markRetrieved(Collection $facts): void
    {
        if ($facts->isEmpty()) {
            return;
        }

        Fact::query()
            ->whereKey($facts->map(fn (RelatedFact $related): int => $related->fact->id))
            ->toBase()
            ->update([
                'retrieval_count' => DB::raw('retrieval_count + 1'),
                'last_retrieved_at' => now(),
            ]);
    }
}
