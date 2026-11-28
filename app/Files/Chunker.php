<?php

namespace App\Files;

use App\Enums\FileKind;
use App\Memory\Tokens;

/**
 * Cuts a file's text into the pieces Archive Memory finds on their own,
 * always at a logical boundary: a heading, a paragraph, a group of rows, a
 * page. All of it is code.
 */
class Chunker
{
    /**
     * @return list<array{heading_path: string|null, body: string}>
     */
    public function chunk(FileKind $kind, string $text): array
    {
        $chunks = match ($kind) {
            FileKind::Markdown => $this->markdown($text),
            FileKind::Csv => $this->csv($text),
            FileKind::Pdf => $this->pdf($text),
            FileKind::Text => $this->paragraphs($text, null),
            FileKind::Image => [],
        };

        return array_values(array_filter($chunks, fn (array $chunk): bool => trim($chunk['body']) !== ''));
    }

    /**
     * By heading, each chunk carrying its heading path. A long section is split by paragraph.
     *
     * @return list<array{heading_path: string|null, body: string}>
     */
    private function markdown(string $text): array
    {
        $lines = Markdown::lines($text);
        $chunks = [];

        foreach (Markdown::pieces($text) as $piece) {
            // The heading's own line is carried as the path, not repeated in the text.
            $from = $piece['path'] === null ? $piece['from'] : $piece['from'] + 1;
            $body = trim(implode("\n", array_slice($lines, $from - 1, $piece['to'] - $from + 1)));

            array_push($chunks, ...$this->paragraphs($body, $piece['path']));
        }

        return $chunks;
    }

    /**
     * Groups of rows, each with the header row, so a chunk can be read on
     * its own. A very long file is only indexed for its first rows, plus one
     * chunk saying what the whole thing is: `query_csv` answers questions
     * about the rest.
     *
     * @return list<array{heading_path: string|null, body: string}>
     */
    private function csv(string $text): array
    {
        $limit = config('donna.files.csv_chunk_rows');
        $budget = config('donna.files.chunk_tokens');
        $csv = Csv::parse($text, $limit);

        if ($csv->columns === []) {
            return [];
        }

        $header = $this->csvLine($csv->columns);
        $chunks = [];
        $group = [];
        $first = 1;

        $flush = function (int $last) use (&$chunks, &$group, &$first, $header): void {
            if ($group !== []) {
                $chunks[] = ['heading_path' => $first === $last ? "Row {$first}" : "Rows {$first}–{$last}", 'body' => $header."\n".implode("\n", $group)];
            }

            $group = [];
            $first = $last + 1;
        };

        foreach ($csv->rows as $index => $row) {
            $line = $this->cut($this->csvLine($row), $budget);

            if ($group !== [] && Tokens::estimate($header."\n".implode("\n", [...$group, $line])) > $budget) {
                $flush($index);
            }

            $group[] = $line;
        }

        $flush(count($csv->rows));

        if ($csv->totalRows > $limit) {
            $chunks[] = [
                'heading_path' => 'Summary',
                'body' => 'Columns: '.implode(', ', $csv->columns)."\n"
                    .number_format($csv->totalRows)." rows. Only the first {$limit} are indexed here.\n"
                    ."First rows:\n".$header."\n".implode("\n", array_map($this->csvLine(...), array_slice($csv->rows, 0, 5))),
            ];
        }

        return $chunks;
    }

    /**
     * By page, split by paragraph when a page is long.
     *
     * @return list<array{heading_path: string|null, body: string}>
     */
    private function pdf(string $text): array
    {
        $chunks = [];

        foreach (explode("\f", $text) as $index => $page) {
            array_push($chunks, ...$this->paragraphs($page, 'Page '.($index + 1)));
        }

        return $chunks;
    }

    /**
     * Blank-line paragraphs packed together up to the token budget.
     *
     * @return list<array{heading_path: string|null, body: string}>
     */
    private function paragraphs(string $text, ?string $path): array
    {
        $budget = config('donna.files.chunk_tokens');
        $text = trim($text);

        if (Tokens::estimate($text) <= $budget) {
            return [['heading_path' => $path, 'body' => $text]];
        }

        $chunks = [];
        $group = '';

        foreach (preg_split('/\R\s*\R/u', $text) as $paragraph) {
            foreach ($this->pieces(trim($paragraph), $budget) as $piece) {
                if ($group !== '' && Tokens::estimate($group."\n\n".$piece) > $budget) {
                    $chunks[] = ['heading_path' => $path, 'body' => $group];
                    $group = '';
                }

                $group = $group === '' ? $piece : $group."\n\n".$piece;
            }
        }

        if ($group !== '') {
            $chunks[] = ['heading_path' => $path, 'body' => $group];
        }

        return $chunks;
    }

    /**
     * A paragraph that is itself over the budget, cut by line and then, for
     * one enormous line, by length.
     *
     * @return list<string>
     */
    private function pieces(string $paragraph, int $budget): array
    {
        if (Tokens::estimate($paragraph) <= $budget) {
            return [$paragraph];
        }

        $pieces = [];
        $group = '';

        foreach (preg_split('/\R/u', $paragraph) as $line) {
            foreach (mb_str_split($line, $budget * 4) ?: [''] as $part) {
                if ($group !== '' && Tokens::estimate($group."\n".$part) > $budget) {
                    $pieces[] = $group;
                    $group = '';
                }

                $group = $group === '' ? $part : $group."\n".$part;
            }
        }

        if ($group !== '') {
            $pieces[] = $group;
        }

        return $pieces;
    }

    /**
     * @param  list<string>  $values
     */
    private function csvLine(array $values): string
    {
        return implode(',', array_map(
            fn (string $value): string => preg_match('/[",\r\n]/', $value) === 1 ? '"'.str_replace('"', '""', $value).'"' : $value,
            $values,
        ));
    }

    /**
     * One row can't be longer than a whole chunk.
     */
    private function cut(string $line, int $budget): string
    {
        return mb_strlen($line) > $budget * 3 ? mb_substr($line, 0, $budget * 3).'…' : $line;
    }
}
