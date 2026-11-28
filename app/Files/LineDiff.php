<?php

namespace App\Files;

/**
 * A line-by-line diff of two versions of a file, for telling Donna what
 * Josh changed by hand. Plain code: the longest common run of lines, after
 * skipping what the two versions share at the start and the end.
 */
class LineDiff
{
    /**
     * The changes as removed (-) and added (+) lines under the line number
     * they start at, or null when the two differ too much to compare cheaply.
     *
     * @param  int  $maxCells  The largest comparison table to build: changed lines in one version times changed lines in the other.
     */
    public static function between(string $old, string $new, int $maxCells): ?string
    {
        $before = Markdown::lines($old);
        $after = Markdown::lines($new);

        $start = 0;

        while ($start < count($before) && $start < count($after) && $before[$start] === $after[$start]) {
            $start++;
        }

        $end = 0;

        while ($end < count($before) - $start && $end < count($after) - $start && $before[count($before) - 1 - $end] === $after[count($after) - 1 - $end]) {
            $end++;
        }

        $removed = array_slice($before, $start, count($before) - $start - $end);
        $added = array_slice($after, $start, count($after) - $start - $end);

        if (count($removed) * count($added) > $maxCells) {
            return null;
        }

        return self::render(self::steps($removed, $added), $start + 1);
    }

    /**
     * @param  list<string>  $old
     * @param  list<string>  $new
     * @return list<array{0: string, 1: string}> Each line with ' ', '-' or '+'.
     */
    private static function steps(array $old, array $new): array
    {
        $rows = count($old);
        $columns = count($new);
        $length = array_fill(0, $rows + 1, array_fill(0, $columns + 1, 0));

        for ($row = $rows - 1; $row >= 0; $row--) {
            for ($column = $columns - 1; $column >= 0; $column--) {
                $length[$row][$column] = $old[$row] === $new[$column]
                    ? $length[$row + 1][$column + 1] + 1
                    : max($length[$row + 1][$column], $length[$row][$column + 1]);
            }
        }

        $steps = [];
        $row = $column = 0;

        while ($row < $rows || $column < $columns) {
            if ($row < $rows && $column < $columns && $old[$row] === $new[$column]) {
                $steps[] = [' ', $old[$row]];
                $row++;
                $column++;
            } elseif ($row < $rows && ($column === $columns || $length[$row + 1][$column] >= $length[$row][$column + 1])) {
                // What was removed is shown before what replaced it.
                $steps[] = ['-', $old[$row]];
                $row++;
            } else {
                $steps[] = ['+', $new[$column]];
                $column++;
            }
        }

        return $steps;
    }

    /**
     * @param  list<array{0: string, 1: string}>  $steps
     * @param  int  $line  The line number, in the new version, the first step is at.
     */
    private static function render(array $steps, int $line): string
    {
        $out = [];
        $inChange = false;

        foreach ($steps as [$sign, $text]) {
            if ($sign === ' ') {
                $inChange = false;
                $line++;

                continue;
            }

            if (! $inChange) {
                $out[] = "@@ line {$line} @@";
                $inChange = true;
            }

            $out[] = $sign.$text;

            if ($sign === '+') {
                $line++;
            }
        }

        return implode("\n", $out);
    }
}
