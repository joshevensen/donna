<?php

namespace App\Files;

/**
 * A CSV file as a header and rows. Parsing is code, like everything done
 * with the rows afterwards: nothing here is a model's guess.
 */
final readonly class Csv
{
    /**
     * @param  list<string>  $columns  The header row, every name unique and not empty.
     * @param  list<list<string>>  $rows  The data rows held, each with one value per column.
     * @param  int  $totalRows  How many data rows the file has, which is more than `$rows` when a limit was given.
     */
    public function __construct(
        public array $columns,
        public array $rows,
        public int $totalRows,
    ) {}

    /**
     * @param  int|null  $limit  Keep only this many rows; the rest are counted, not held.
     */
    public static function parse(string $text, ?int $limit = null): self
    {
        $stream = fopen('php://temp', 'r+');
        fwrite($stream, $text);
        rewind($stream);

        $delimiter = self::delimiter($text);
        $columns = null;
        $rows = [];
        $total = 0;

        while (($record = fgetcsv($stream, null, $delimiter, '"', '')) !== false) {
            // A blank line comes back as one null field.
            if ($record === [null]) {
                continue;
            }

            if ($columns === null) {
                $columns = self::header($record);

                continue;
            }

            $total++;

            if ($limit === null || count($rows) < $limit) {
                $rows[] = self::fit($record, count($columns));
            }
        }

        fclose($stream);

        return new self($columns ?? [], $rows, $total);
    }

    /**
     * The column's position, matched by name without regard to case or
     * surrounding space, or null when there is no such column.
     */
    public function indexOf(string $column): ?int
    {
        $wanted = mb_strtolower(trim($column));

        foreach ($this->columns as $index => $name) {
            if (mb_strtolower($name) === $wanted) {
                return $index;
            }
        }

        return null;
    }

    /**
     * Exports from outside the US are often split on semicolons, and some
     * tools write tabs. The header line says which.
     */
    private static function delimiter(string $text): string
    {
        $header = strtok($text, "\r\n") ?: '';

        $counts = [
            ',' => substr_count($header, ','),
            ';' => substr_count($header, ';'),
            "\t" => substr_count($header, "\t"),
        ];

        arsort($counts);

        return array_key_first($counts);
    }

    /**
     * @param  list<string|null>  $record
     * @return list<string>
     */
    private static function header(array $record): array
    {
        $names = [];

        foreach ($record as $index => $name) {
            $name = trim((string) $name);
            $name = $name === '' ? 'column_'.($index + 1) : $name;

            $unique = $name;
            $copy = 2;

            while (in_array(mb_strtolower($unique), array_map(mb_strtolower(...), $names), true)) {
                $unique = "{$name}_{$copy}";
                $copy++;
            }

            $names[] = $unique;
        }

        return $names;
    }

    /**
     * @param  list<string|null>  $record
     * @return list<string>
     */
    private static function fit(array $record, int $width): array
    {
        $values = array_map(fn (?string $value): string => $value ?? '', array_slice($record, 0, $width));

        return array_pad($values, $width, '');
    }
}
