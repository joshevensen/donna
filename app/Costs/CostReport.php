<?php

namespace App\Costs;

use App\Enums\CostPeriod;
use App\Models\Cost;
use Carbon\CarbonImmutable;

/**
 * "What did you cost me this month?" answered by a query. Periods follow
 * Josh's calendar, not UTC.
 */
class CostReport
{
    /**
     * @return array{
     *     period: string,
     *     from: string|null,
     *     until: string,
     *     total_usd: string,
     *     calls: int,
     *     web_searches: int,
     *     by_purpose: array<string, array{usd: string, calls: int}>,
     * }
     */
    public function for(CostPeriod $period): array
    {
        [$from, $until] = $period->bounds(CarbonImmutable::now(config('donna.timezone')));

        $rows = Cost::query()
            ->toBase()
            ->when($from, fn ($query) => $query->where('created_at', '>=', $from->utc()))
            ->where('created_at', '<', $until->utc())
            ->groupByRaw('rollup(purpose)')
            ->selectRaw('purpose, grouping(purpose) as is_total, count(*) as calls')
            ->selectRaw('coalesce(sum(usd), 0) as usd, coalesce(sum(web_search_requests), 0) as web_searches')
            ->get();

        $total = $rows->firstWhere('is_total', 1);

        return [
            'period' => $period->value,
            'from' => $from?->toDateString(),
            'until' => $until->subDay()->toDateString(),
            'total_usd' => number_format((float) ($total->usd ?? 0), 2, '.', ''),
            'calls' => (int) ($total->calls ?? 0),
            'web_searches' => (int) ($total->web_searches ?? 0),
            'by_purpose' => $rows->where('is_total', 0)->sortBy('purpose')->mapWithKeys(fn (object $row): array => [
                $row->purpose => [
                    'usd' => number_format((float) $row->usd, 2, '.', ''),
                    'calls' => (int) $row->calls,
                ],
            ])->all(),
        ];
    }
}
