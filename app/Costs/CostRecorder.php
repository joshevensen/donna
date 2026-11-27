<?php

namespace App\Costs;

use App\Enums\CostPurpose;
use App\Models\Cost;
use Illuminate\Database\Eloquent\Model;
use RuntimeException;

/**
 * The one place a Cost row is written. Every model call ends up here once.
 */
class CostRecorder
{
    public function __construct(private Pricing $pricing) {}

    /**
     * Record one model call.
     *
     * A model missing from the pricing table is still recorded (the call
     * happened) at $0, and reported so the table gets fixed.
     */
    public function record(
        CostPurpose $purpose,
        string $provider,
        string $model,
        int $inputTokens,
        int $outputTokens = 0,
        int $cacheReadTokens = 0,
        int $cacheWriteTokens = 0,
        int $webSearchRequests = 0,
        bool $batched = false,
        ?Model $trigger = null,
    ): Cost {
        $usd = $this->pricing->price(
            $model, $inputTokens, $outputTokens, $cacheReadTokens, $cacheWriteTokens, $webSearchRequests, $batched,
        );

        if ($usd === null) {
            report(new RuntimeException("No pricing configured for model [{$model}]; recorded its cost as \$0."));
        }

        return Cost::create([
            'purpose' => $purpose,
            'provider' => $provider,
            'model' => $model,
            'input_tokens' => $inputTokens,
            'output_tokens' => $outputTokens,
            'cache_read_tokens' => $cacheReadTokens,
            'cache_write_tokens' => $cacheWriteTokens,
            'web_search_requests' => $webSearchRequests,
            'batched' => $batched,
            'usd' => $usd ?? '0',
            'trigger_type' => $trigger?->getMorphClass(),
            'trigger_id' => $trigger?->getKey(),
        ]);
    }
}
