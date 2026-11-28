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
     * meaning to count at all: a chunk is hundreds of tokens, and a long
     * one shares a word or two with almost anything Josh says, so without
     * a floor a few unrelated ones went into every prompt. Its words then
     * only decide the order. When the message has no embedding (embeddings
     * were down) words are all there is, and they are used alone.
     *
     * @param  bool  $nearOnly  False for a search she asked for herself (`recall`), where anything matching is wanted.
     * @param  list<float>|null  $embedding
     * @param  list<int>  $exceptFileIds
     * @return array<int, float> Scores keyed by chunk ID, best first.
     */
    public function chunks(string $text, ?array $embedding, array $exceptFileIds = [], bool $nearOnly = true): array
    {
        return $this->search(
            FileChunk::query()
                ->whereIn('file_id', File::query()->kept()->select('id'))
                ->whereNotIn('file_id', $exceptFileIds),
            $text,
            $embedding,
            $nearOnly ? config('donna.context.chunk_max_distance') : null,
        );
    }

    /**
     * @param  Builder<Message>|Builder<Fact>|Builder<FileChunk>  $query
     * @param  list<float>|null  $embedding
     * @param  float|null  $maxDistance  Leave out rows further than this by meaning, when there is a meaning to compare.
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
            ->when($maxDistance !== null && $embedding !== null, fn (Builder $query) => $query
                ->whereNotNull('embedding')
                ->whereVectorDistanceLessThan('embedding', $embedding, $maxDistance))
            ->whereRaw('search @@ '.self::ANY_WORD_QUERY, [$text])
            ->orderByRaw('ts_rank_cd(search, '.self::ANY_WORD_QUERY.') desc', [$text])
            ->orderByDesc('id')
            ->limit($candidates)
            ->pluck('id')
            ->all();

        return RankFusion::fuse([$byMeaning, $byWords], config('donna.context.rrf_k'));
    }
}
