<?php

namespace App\Costs;

/**
 * Turns token counts into dollars from the table in config/donna.php.
 */
class Pricing
{
    /**
     * Price one model call in USD.
     *
     * `$inputTokens` is the whole prompt; the cache counts are subsets of it,
     * each billed at its own rate. `$cacheWriteOneHourTokens` is the part of
     * the cache writes that used the one-hour cache, which costs more. The
     * batch discount applies to tokens only.
     *
     * @return numeric-string|null Null when the model has no pricing entry.
     */
    public function price(
        string $model,
        int $inputTokens,
        int $outputTokens,
        int $cacheReadTokens = 0,
        int $cacheWriteTokens = 0,
        int $webSearchRequests = 0,
        bool $batched = false,
        int $cacheWriteOneHourTokens = 0,
    ): ?string {
        $rates = $this->ratesFor($model, $inputTokens);

        if ($rates === null) {
            return null;
        }

        $uncachedTokens = max(0, $inputTokens - $cacheReadTokens - $cacheWriteTokens);
        $oneHour = min($cacheWriteOneHourTokens, $cacheWriteTokens);

        $tokens = (
            $uncachedTokens * $rates['input']
            + ($cacheWriteTokens - $oneHour) * $rates['cache_write']
            + $oneHour * ($rates['cache_write_1h'] ?? $rates['cache_write'])
            + $cacheReadTokens * $rates['cache_read']
            + $outputTokens * $rates['output']
        ) / 1_000_000;

        if ($batched) {
            $tokens *= config('donna.pricing.batch_discount');
        }

        $searches = $webSearchRequests * config('donna.pricing.web_search_per_request');

        return number_format($tokens + $searches, 6, '.', '');
    }

    /**
     * The per-million rates that apply to a request of the given prompt size.
     *
     * @return array{input: float, cache_write: float, cache_write_1h?: float, cache_read: float, output: float}|null
     */
    private function ratesFor(string $model, int $inputTokens): ?array
    {
        $rates = $this->entryFor($model);

        if ($rates === null) {
            return null;
        }

        $longContext = $rates['long_context'] ?? null;

        return $longContext !== null && $inputTokens > $longContext['threshold']
            ? $longContext
            : $rates;
    }

    /**
     * Find the model's entry, letting a dated ID such as
     * "claude-sonnet-5-5-20261001" match its undated one.
     *
     * @return array<string, mixed>|null
     */
    private function entryFor(string $model): ?array
    {
        $models = config('donna.pricing.models');

        if (isset($models[$model])) {
            return $models[$model];
        }

        return collect($models)
            ->filter(fn (array $rates, string $name): bool => str_starts_with($model, $name.'-'))
            ->sortByDesc(fn (array $rates, string $name): int => strlen($name))
            ->first();
    }
}
