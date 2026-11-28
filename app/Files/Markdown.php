<?php

namespace App\Files;

use App\Support\CodeFences;

/**
 * Reads a markdown file's structure: its headings and the sections under
 * them. Chunking, outlines and "rewrite this section" all cut at the same
 * places, and a `#` inside a code block is never a heading.
 */
class Markdown
{
    /**
     * Every heading with where it is.
     *
     * @return list<array{line: int, level: int, title: string, path: string}> `line` counts from 1; `path` is the heading with its parents, e.g. "Pricing › Wholesale".
     */
    public static function headings(string $text): array
    {
        $headings = [];
        $trail = [];

        foreach (CodeFences::lines($text) as $index => ['line' => $line, 'code' => $code]) {
            if ($code || preg_match('/^ {0,3}(#{1,6})[ \t]+(.+?)[ \t]*#*[ \t]*$/u', $line, $match) !== 1) {
                continue;
            }

            $level = strlen($match[1]);
            $title = trim($match[2]);

            $trail = array_filter($trail, fn (int $depth): bool => $depth < $level, ARRAY_FILTER_USE_KEY);
            $trail[$level] = $title;

            $headings[] = ['line' => $index + 1, 'level' => $level, 'title' => $title, 'path' => implode(' › ', $trail)];
        }

        return $headings;
    }

    /**
     * The file cut at every heading: each piece is a heading's own lines, up
     * to the next heading of any level. Text before the first heading is a
     * piece with no heading.
     *
     * @return list<array{path: string|null, from: int, to: int}> Line numbers from 1, inclusive; `from` is the heading's own line.
     */
    public static function pieces(string $text): array
    {
        $total = count(self::lines($text));
        $headings = self::headings($text);
        $pieces = [];

        $firstHeading = $headings[0]['line'] ?? $total + 1;

        if ($firstHeading > 1) {
            $pieces[] = ['path' => null, 'from' => 1, 'to' => $firstHeading - 1];
        }

        foreach ($headings as $index => $heading) {
            $pieces[] = [
                'path' => $heading['path'],
                'from' => $heading['line'],
                'to' => ($headings[$index + 1]['line'] ?? $total + 1) - 1,
            ];
        }

        return $pieces;
    }

    /**
     * The whole section a heading opens: its own text and every deeper
     * heading under it, up to the next heading of the same or a higher level.
     *
     * @param  array{line: int, level: int, title: string, path: string}  $heading
     * @return array{from: int, to: int} Line numbers from 1, inclusive; `from` is the heading's own line.
     */
    public static function section(string $text, array $heading): array
    {
        $end = count(self::lines($text));

        foreach (self::headings($text) as $other) {
            if ($other['line'] > $heading['line'] && $other['level'] <= $heading['level']) {
                $end = $other['line'] - 1;

                break;
            }
        }

        return ['from' => $heading['line'], 'to' => $end];
    }

    /**
     * The headings a name could mean: an exact title, or a full path
     * ("Pricing › Wholesale", or with ">" for the arrow), ignoring case.
     *
     * @return list<array{line: int, level: int, title: string, path: string}>
     */
    public static function find(string $text, string $name): array
    {
        $wanted = self::normalize($name);

        return array_values(array_filter(
            self::headings($text),
            fn (array $heading): bool => self::normalize($heading['title']) === $wanted || self::normalize($heading['path']) === $wanted,
        ));
    }

    /**
     * @return list<string>
     */
    public static function lines(string $text): array
    {
        return preg_split('/\R/u', $text);
    }

    /**
     * The line ending the file uses, so an edit doesn't change the ones it didn't touch.
     */
    public static function lineEnding(string $text): string
    {
        return str_contains($text, "\r\n") ? "\r\n" : "\n";
    }

    private static function normalize(string $name): string
    {
        $name = preg_replace('/^#+\s*/', '', trim($name));

        return mb_strtolower(trim(preg_replace('/\s*(›|>)\s*/u', ' › ', $name)));
    }
}
