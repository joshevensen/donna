<?php

namespace App\Memory;

use App\Costs\CostRecorder;
use App\Enums\CostPurpose;
use Illuminate\Database\Eloquent\Model;
use Laravel\Ai\Embeddings;
use Throwable;

/**
 * The only place embeddings are generated, so each call records its Cost.
 */
class Embedder
{
    public function __construct(private CostRecorder $costs) {}

    /**
     * Embed one piece of text.
     *
     * @return list<float>
     */
    public function embed(string $text, ?Model $trigger = null): array
    {
        return $this->embedMany([$text], $trigger)[0];
    }

    /**
     * Embed one piece of text, or return null if embeddings are unavailable.
     *
     * For paths that must not fail because Voyage is down: the row is saved
     * without a vector, stays findable by full-text search, and is embedded
     * later by the EmbedMissing job.
     *
     * @return list<float>|null
     */
    public function attempt(string $text, ?Model $trigger = null): ?array
    {
        try {
            return $this->embed($text, $trigger);
        } catch (Throwable $exception) {
            report($exception);

            return null;
        }
    }

    /**
     * Embed several texts in a single call, in order.
     *
     * @param  list<string>  $texts
     * @return list<list<float>>
     */
    public function embedMany(array $texts, ?Model $trigger = null): array
    {
        if ($texts === []) {
            return [];
        }

        $config = config('donna.models.embedding');

        $response = Embeddings::for($texts)
            ->dimensions($config['dimensions'])
            ->generate($config['provider'], $config['model']);

        $this->costs->record(
            purpose: CostPurpose::Embedding,
            provider: $response->meta->provider ?? $config['provider'],
            model: $response->meta->model ?? $config['model'],
            inputTokens: $response->usage->inputTokens,
            trigger: $trigger,
        );

        return $response->embeddings;
    }
}
