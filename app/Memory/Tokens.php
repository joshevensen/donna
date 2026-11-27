<?php

namespace App\Memory;

/**
 * A cheap token estimate for sizing prompts: about four characters each.
 * Good enough to keep context within budget; never used for costs.
 */
class Tokens
{
    public static function estimate(string $text): int
    {
        return (int) ceil(mb_strlen($text) / 4);
    }
}
