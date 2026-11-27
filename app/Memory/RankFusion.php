<?php

namespace App\Memory;

/**
 * Reciprocal rank fusion: merges ranked lists that score on different
 * scales (cosine distance, ts_rank) using nothing but each item's position.
 */
class RankFusion
{
    /**
     * @param  list<list<int>>  $rankings  Each list holds IDs, best first.
     * @return array<int, float> Fused scores keyed by ID, best first.
     */
    public static function fuse(array $rankings, int $k): array
    {
        $scores = [];

        foreach ($rankings as $ranking) {
            foreach (array_values($ranking) as $position => $id) {
                $scores[$id] = ($scores[$id] ?? 0.0) + 1 / ($k + $position + 1);
            }
        }

        arsort($scores);

        return $scores;
    }
}
