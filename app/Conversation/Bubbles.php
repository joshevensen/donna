<?php

namespace App\Conversation;

use App\Support\CodeFences;

/**
 * Splits one reply into the texts it is shown as. Donna decides when a reply
 * reads better as several short messages and marks the breaks herself, with
 * a line holding only `---`. Cutting there is code.
 */
class Bubbles
{
    /**
     * The separator Donna writes on a line of its own.
     */
    public const string SEPARATOR = '---';

    /**
     * @return list<string> One entry per bubble, trimmed; none are empty.
     */
    public static function split(string $body): array
    {
        $bubbles = [[]];

        foreach (CodeFences::lines($body) as ['line' => $line, 'code' => $code]) {
            if (! $code && rtrim($line) === self::SEPARATOR) {
                $bubbles[] = [];

                continue;
            }

            $bubbles[array_key_last($bubbles)][] = $line;
        }

        return collect($bubbles)
            ->map(fn (array $lines): string => trim(implode("\n", $lines)))
            ->reject(fn (string $bubble): bool => $bubble === '')
            ->values()
            ->all();
    }
}
