<?php

namespace App\Files;

use App\Enums\FileKind;
use App\Memory\Tokens;
use App\Models\File;
use Illuminate\Support\Number;

/**
 * Turns a file's text into what Donna is shown of it: numbered lines, or,
 * for a file too long to hand over whole, an outline she can read into.
 */
class FileText
{
    /**
     * Lines with their numbers, as `read_file` returns them and `edit_file` counts them.
     *
     * @param  list<string>  $lines
     * @param  int  $from  The number of the first line given.
     */
    public static function numbered(array $lines, int $from = 1): string
    {
        $width = strlen((string) ($from + count($lines) - 1));

        return implode("\n", array_map(
            fn (string $line, int $index): string => str_pad((string) ($from + $index), $width, ' ', STR_PAD_LEFT).'| '.$line,
            $lines,
            array_keys($lines),
        ));
    }

    /**
     * A file's text as the lines Donna counts in. A PDF's pages are marked
     * where they start instead of with the form feeds they are stored with.
     *
     * @return list<string>
     */
    public static function lines(File $file, string $body): array
    {
        if ($file->kind !== FileKind::Pdf) {
            return Markdown::lines($body);
        }

        $lines = [];

        foreach (explode("\f", $body) as $index => $page) {
            array_push($lines, '--- Page '.($index + 1).' ---', ...Markdown::lines($page));
        }

        return $lines;
    }

    /**
     * What stands in for a file too long to give whole, and how much of it that is.
     *
     * @return array{text: string, given: string}
     */
    public static function outline(File $file, string $body): array
    {
        $lines = self::lines($file, $body);
        $total = count($lines);
        $shown = min($total, config('donna.files.outline_lines'));
        $opening = self::fit(array_slice($lines, 0, $shown));

        if ($file->kind === FileKind::Csv) {
            $rows = config('donna.files.outline_rows');
            $csv = Csv::parse($body, $rows);

            return [
                'given' => 'outline only: '.count($csv->rows).' of '.number_format($csv->totalRows).' rows',
                'text' => 'Columns: '.implode(', ', $csv->columns)."\n"
                    .number_format($csv->totalRows).' rows. The first '.count($csv->rows).":\n"
                    .self::fit(array_slice($lines, 0, count($csv->rows) + 1))."\n"
                    .'Use query_csv for totals, counts and filtered rows; never estimate them from this sample.',
            ];
        }

        $headings = $file->kind === FileKind::Markdown
            ? collect(Markdown::headings($body))->take(80)->map(fn (array $heading): string => "line {$heading['line']}: ".str_repeat('#', $heading['level'])." {$heading['title']}")
            : collect();

        return [
            'given' => "outline only: {$shown} of ".number_format($total).' lines',
            'text' => implode("\n", array_filter([
                $file->kind === FileKind::Pdf ? number_format($file->meta['pages'] ?? 0).' pages.' : null,
                $headings->isEmpty() ? null : "Headings:\n".$headings->implode("\n"),
                "The first {$shown} lines:\n".$opening,
                'Use read_file to read the rest, by heading or by line.',
            ])),
        ];
    }

    /**
     * A line in the memory block saying what a file is.
     */
    public static function describe(File $file): string
    {
        $meta = $file->meta ?? [];

        return implode(', ', array_filter([
            $file->kind->label(),
            Number::fileSize($file->size_bytes, maxPrecision: 1),
            isset($meta['rows']) ? number_format($meta['rows']).' rows' : null,
            isset($meta['pages']) ? number_format($meta['pages']).' '.($meta['pages'] === 1 ? 'page' : 'pages') : null,
            ($meta['has_text'] ?? true) ? null : 'a scan with no text',
            isset($meta['width'], $meta['height']) ? "{$meta['width']}×{$meta['height']}" : null,
        ]));
    }

    /**
     * Lines, with any single enormous one cut short so a sample stays a sample.
     *
     * @param  list<string>  $lines
     */
    private static function fit(array $lines): string
    {
        $budget = config('donna.files.outline_tokens');
        $text = implode("\n", array_map(fn (string $line): string => mb_strlen($line) > 400 ? mb_substr($line, 0, 400).'…' : $line, $lines));

        return Tokens::estimate($text) > $budget ? mb_substr($text, 0, $budget * 4).'…' : $text;
    }
}
