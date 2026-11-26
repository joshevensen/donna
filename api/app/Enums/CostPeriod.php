<?php

namespace App\Enums;

use Carbon\CarbonImmutable;

enum CostPeriod: string
{
    case Today = 'today';
    case Yesterday = 'yesterday';
    case ThisWeek = 'this_week';
    case ThisMonth = 'this_month';
    case LastMonth = 'last_month';
    case AllTime = 'all_time';

    /**
     * The period's bounds for someone whose clock reads `$now`.
     *
     * @return array{0: CarbonImmutable|null, 1: CarbonImmutable} Inclusive start (null for all time) and exclusive end.
     */
    public function bounds(CarbonImmutable $now): array
    {
        return match ($this) {
            self::Today => [$now->startOfDay(), $now->addDay()->startOfDay()],
            self::Yesterday => [$now->subDay()->startOfDay(), $now->startOfDay()],
            self::ThisWeek => [$now->startOfWeek(), $now->addWeek()->startOfWeek()],
            self::ThisMonth => [$now->startOfMonth(), $now->addMonthNoOverflow()->startOfMonth()],
            self::LastMonth => [$now->subMonthNoOverflow()->startOfMonth(), $now->startOfMonth()],
            self::AllTime => [null, $now->addDay()->startOfDay()],
        };
    }
}
