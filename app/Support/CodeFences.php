<?php

namespace App\Support;

/**
 * Walks markdown line by line, telling prose from fenced code. A `---` or an
 * `@name` inside a code block is code, not a bubble break or a mention.
 */
class CodeFences
{
    /**
     * Each line of the text with whether it is part of a fenced code block.
     * The fence lines themselves count as code.
     *
     * @return list<array{line: string, code: bool}>
     */
    public static function lines(string $text): array
    {
        $lines = [];
        $fence = null;

        foreach (preg_split('/\r\n|\n|\r/', $text) as $line) {
            if ($fence === null && preg_match('/^ {0,3}(`{3,}|~{3,})/', $line, $opening) === 1) {
                $fence = $opening[1];
                $lines[] = ['line' => $line, 'code' => true];

                continue;
            }

            $lines[] = ['line' => $line, 'code' => $fence !== null];

            // A block closes on a fence of the same character, at least as long.
            if ($fence !== null && preg_match('/^ {0,3}'.preg_quote($fence[0], '/').'{'.strlen($fence).',}\s*$/', $line) === 1) {
                $fence = null;
            }
        }

        return $lines;
    }

    /**
     * The text with fenced blocks and inline code spans removed.
     */
    public static function strip(string $text): string
    {
        $prose = collect(self::lines($text))
            ->reject(fn (array $line): bool => $line['code'])
            ->pluck('line')
            ->implode("\n");

        return preg_replace('/(`+).+?\1/su', ' ', $prose);
    }
}
