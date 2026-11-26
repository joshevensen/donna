<?php

namespace App\Memory;

use App\Costs\CostRecorder;
use App\Enums\CostPurpose;
use Illuminate\Database\Eloquent\Model;
use Laravel\Ai\Embeddings;

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
