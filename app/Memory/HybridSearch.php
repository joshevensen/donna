<?php

namespace App\Memory;

use App\Models\Fact;
use App\Models\File;
use App\Models\FileChunk;
use App\Models\Message;
use Illuminate\Database\Eloquent\Builder;

/**
 * Hybrid retrieval: the nearest rows by embedding and the best rows by
 * full-text rank, merged with reciprocal rank fusion.
 */
class HybridSearch
{
    /**
     * A tsquery matching any word of the text rather than all of them: a
     * whole chat message is the query, and no past message contains every
     * word of it.
     */
    private const string ANY_WORD_QUERY = "replace(plainto_tsquery('english', ?)::text, '&', '|')::tsquery";

    /**
     * Score complete messages against the text.
     *
     * @param  list<float>|null  $embedding
     * @param  list<int>  $exceptIds
     * @return array<int, float> Scores keyed by message ID, best first.
     */
    public function messages(string $text, ?array $embedding, array $exceptIds = []): array
    {
        return $this->search(Message::query()->complete()->whereKeyNot($exceptIds), $text, $embedding);
    }

    /**
     * Score active facts against the text.
     *
     * @param  list<float>|null  $embedding
     * @return array<int, float> Scores keyed by fact ID, best first.
     */
    public function facts(string $text, ?array $embedding): array
    {
        return $this->search(Fact::query()->active(), $text, $embedding);
    }

    /**
     * Score the chunks of kept files against the text. A file that is
     * deleted or no longer kept contributes nothing.
     *
     * Unlike messages and facts, a chunk has to be near the message in
     * meaning (or share its words) to count at all: a chunk is hundreds of
     * tokens, so the nearest few of an unrelated library are not worth
     * putting in every prompt.
     *
     * @param  list<float>|null  $embedding
     * @param  list<int>  $exceptFileIds
     * @return array<int, float> Scores keyed by chunk ID, best first.
     */
    public function chunks(string $text, ?array $embedding, array $exceptFileIds = []): array
    {
        return $this->search(
            FileChunk::query()
                ->whereIn('file_id', File::query()->kept()->select('id'))
                ->whereNotIn('file_id', $exceptFileIds),
            $text,
            $embedding,
            config('donna.context.chunk_max_distance'),
        );
    }

    /**
     * @param  Builder<Message>|Builder<Fact>|Builder<FileChunk>  $query
     * @param  list<float>|null  $embedding
     * @param  float|null  $maxDistance  Leave out rows further than this by meaning; they can still match by their words.
     * @return array<int, float>
     */
    private function search(Builder $query, string $text, ?array $embedding, ?float $maxDistance = null): array
    {
        $candidates = config('donna.context.search_candidates');

        $byMeaning = $embedding === null ? [] : (clone $query)
            ->whereNotNull('embedding')
            ->when($maxDistance !== null, fn (Builder $query) => $query->whereVectorDistanceLessThan('embedding', $embedding, $maxDistance))
            ->orderByVectorDistance('embedding', $embedding)
            ->limit($candidates)
            ->pluck('id')
            ->all();

        $byWords = trim($text) === '' ? [] : (clone $query)
            ->whereRaw('search @@ '.self::ANY_WORD_QUERY, [$text])
            ->orderByRaw('ts_rank_cd(search, '.self::ANY_WORD_QUERY.') desc', [$text])
            ->orderByDesc('id')
            ->limit($candidates)
            ->pluck('id')
            ->all();

        return RankFusion::fuse([$byMeaning, $byWords], config('donna.context.rrf_k'));
    }
}
