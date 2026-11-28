<?php

namespace App\Files;

use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use InvalidArgumentException;

/**
 * Answers questions about a CSV in code: filter, group, count, sum,
 * average, sort. Donna decides which question to ask; she never does the
 * arithmetic. Sums are exact decimal arithmetic, not floating point, so
 * money adds up to the cent.
 */
class CsvQuery
{
    public const array OPERATORS = ['=', '!=', '>', '>=', '<', '<=', 'contains', 'not_contains', 'starts_with', 'ends_with', 'in', 'empty', 'not_empty'];

    public const array FUNCTIONS = ['count', 'sum', 'avg', 'min', 'max'];

    /**
     * @param  array{
     *     where?: list<array{column: string, op: string, value?: mixed, values?: list<string>}>,
     *     group_by?: list<string>,
     *     aggregate?: list<array{fn: string, column?: string|null}>,
     *     order_by?: list<array{column: string, direction?: string|null}>,
     * }  $query
     * @return array{columns: list<string>, rows: list<list<string>>, matched: int, total: int, notes: list<string>} `matched` is how many of the file's rows passed the filter; `total` how many result rows there are before `$limit`.
     *
     * @throws InvalidArgumentException When the query names a column or operator that doesn't exist.
     */
    public function run(Csv $csv, array $query, int $limit): array
    {
        $notes = [];
        $rows = $this->filter($csv, $query['where'] ?? []);
        $matched = count($rows);

        $groupBy = $query['group_by'] ?? [];
        $aggregates = $query['aggregate'] ?? [];

        if ($groupBy !== [] || $aggregates !== []) {
            [$columns, $rows] = $this->aggregate($csv, $rows, $groupBy, $aggregates === [] ? [['fn' => 'count']] : $aggregates, $notes);
        } else {
            $columns = $csv->columns;
        }

        $rows = $this->sort($columns, $rows, $query['order_by'] ?? []);

        return [
            'columns' => $columns,
            'rows' => array_slice($rows, 0, $limit),
            'matched' => $matched,
            'total' => count($rows),
            'notes' => $notes,
        ];
    }

    /**
     * A value read as a number, or null when it isn't one. Currency signs,
     * thousands separators, a percent sign and accountants' parentheses for
     * a negative are all understood.
     */
    public static function number(string $value): ?BigDecimal
    {
        $clean = preg_replace('/[\s$€£]|%$/u', '', trim($value));

        if (preg_match('/^\((.+)\)$/', $clean, $match) === 1) {
            $clean = '-'.$match[1];
        }

        // "12,50" and "1.000,00" are how much of Europe writes 12.50 and 1000.00: a comma
        // with one or two digits after it is a decimal point, never a thousands separator.
        $clean = preg_match('/^[+-]?(\d{1,3}(\.\d{3})+|\d+),\d{1,2}$/', $clean) === 1
            ? str_replace(',', '.', str_replace('.', '', $clean))
            : str_replace(',', '', $clean);

        return preg_match('/^[+-]?(\d+(\.\d*)?|\.\d+)$/', $clean) === 1 ? BigDecimal::of(rtrim($clean, '.')) : null;
    }

    /**
     * @param  list<array{column: string, op: string, value?: mixed}>  $conditions
     * @return list<list<string>>
     */
    private function filter(Csv $csv, array $conditions): array
    {
        $tests = array_map(function (array $condition) use ($csv): array {
            if (! in_array($condition['op'], self::OPERATORS, true)) {
                throw new InvalidArgumentException("\"{$condition['op']}\" isn't an operator. Use one of: ".implode(', ', self::OPERATORS).'.');
            }

            $value = $condition['op'] === 'in' ? ($condition['values'] ?? $condition['value'] ?? []) : ($condition['value'] ?? null);

            if (is_array($value) && $condition['op'] !== 'in') {
                throw new InvalidArgumentException("\"{$condition['op']}\" compares with one value, not a list. Use \"in\" with values for a list.");
            }

            return [$this->column($csv->columns, $condition['column']), $condition['op'], $value];
        }, $conditions);

        return array_values(array_filter($csv->rows, function (array $row) use ($tests): bool {
            foreach ($tests as [$index, $operator, $value]) {
                if (! $this->passes($row[$index], $operator, $value)) {
                    return false;
                }
            }

            return true;
        }));
    }

    private function passes(string $cell, string $operator, mixed $value): bool
    {
        $text = mb_strtolower(trim($cell));
        $wanted = is_array($value) ? '' : mb_strtolower(trim((string) $value));

        return match ($operator) {
            '=' => $this->compare($cell, (string) $value) === 0,
            '!=' => $this->compare($cell, (string) $value) !== 0,
            '>' => $this->ordered($cell, (string) $value) && $this->compare($cell, (string) $value) > 0,
            '>=' => $this->ordered($cell, (string) $value) && $this->compare($cell, (string) $value) >= 0,
            '<' => $this->ordered($cell, (string) $value) && $this->compare($cell, (string) $value) < 0,
            '<=' => $this->ordered($cell, (string) $value) && $this->compare($cell, (string) $value) <= 0,
            'contains' => $wanted !== '' && str_contains($text, $wanted),
            'not_contains' => $wanted === '' || ! str_contains($text, $wanted),
            'starts_with' => $wanted !== '' && str_starts_with($text, $wanted),
            'ends_with' => $wanted !== '' && str_ends_with($text, $wanted),
            'in' => collect($this->options($value))->contains(fn (string $option): bool => $this->compare($cell, $option) === 0),
            'empty' => $text === '',
            'not_empty' => $text !== '',
        };
    }

    /**
     * The values an "in" condition lists. The model may send a list, or one
     * string holding a JSON list or values separated by commas.
     *
     * @return list<string>
     */
    private function options(mixed $value): array
    {
        if (is_string($value)) {
            $decoded = json_decode($value, true);
            $value = is_array($decoded) ? $decoded : explode(',', $value);
        }

        return array_values(array_map(fn (mixed $option): string => trim((string) $option), array_filter((array) $value, is_scalar(...))));
    }

    /**
     * Whether a cell can be more or less than the value at all: an empty
     * cell can't, and "n/a" is not more than 1000.
     */
    private function ordered(string $cell, string $value): bool
    {
        return match (true) {
            trim($cell) === '' => false,
            self::number($value) !== null => self::number($cell) !== null,
            $this->date($value) !== null => $this->date($cell) !== null,
            default => true,
        };
    }

    /**
     * Compare two values as numbers when both are numbers, as dates when
     * both are dates, and otherwise as text without regard to case.
     */
    private function compare(string $one, string $other): int
    {
        $numbers = [self::number($one), self::number($other)];

        if ($numbers[0] !== null && $numbers[1] !== null) {
            return $numbers[0]->compareTo($numbers[1]);
        }

        $dates = [$this->date($one), $this->date($other)];

        if ($dates[0] !== null && $dates[1] !== null) {
            return $dates[0] <=> $dates[1];
        }

        return strcmp(mb_strtolower(trim($one)), mb_strtolower(trim($other))) <=> 0;
    }

    /**
     * A value read as a date, or null. Only things that look like dates are
     * tried, so "may" or "sat" in ordinary text isn't mistaken for one.
     */
    private function date(string $value): ?int
    {
        $value = trim($value);

        // A year-first date, a three-part date, or a month by name. "1001-2" is an order number, not a date.
        if (preg_match('/^\d{4}-\d{1,2}-\d{1,2}|^\d{1,2}[-\/.]\d{1,2}[-\/.]\d{2,4}|\b(jan|feb|mar|apr|may|jun|jul|aug|sep|oct|nov|dec)[a-z]*\.?\s+\d|\d\s+(jan|feb|mar|apr|may|jun|jul|aug|sep|oct|nov|dec)/i', $value) !== 1) {
            return null;
        }

        $time = strtotime($value);

        return $time === false ? null : $time;
    }

    /**
     * @param  list<list<string>>  $rows
     * @param  list<string>  $groupBy
     * @param  list<array{fn: string, column?: string|null}>  $aggregates
     * @param  list<string>  $notes
     * @return array{0: list<string>, 1: list<list<string>>}
     */
    private function aggregate(Csv $csv, array $rows, array $groupBy, array $aggregates, array &$notes): array
    {
        $groupIndexes = array_map(fn (string $column): int => $this->column($csv->columns, $column), $groupBy);

        $plans = array_map(function (array $aggregate) use ($csv): array {
            $function = mb_strtolower($aggregate['fn']);

            if (! in_array($function, self::FUNCTIONS, true)) {
                throw new InvalidArgumentException("\"{$aggregate['fn']}\" isn't something to compute. Use one of: ".implode(', ', self::FUNCTIONS).'.');
            }

            if ($function !== 'count' && blank($aggregate['column'] ?? null)) {
                throw new InvalidArgumentException("{$function} needs a column.");
            }

            $index = filled($aggregate['column'] ?? null) ? $this->column($csv->columns, $aggregate['column']) : null;

            return [$function, $index, $index === null ? 'count' : "{$function}({$csv->columns[$index]})"];
        }, $aggregates);

        $groups = [];

        foreach ($rows as $row) {
            $key = array_map(fn (int $index): string => $row[$index], $groupIndexes);
            $groups[json_encode($key)] ??= ['key' => $key, 'rows' => []];
            $groups[json_encode($key)]['rows'][] = $row;
        }

        // With nothing to group by there is one group, even when it is empty.
        if ($groupIndexes === [] && $groups === []) {
            $groups['[]'] = ['key' => [], 'rows' => []];
        }

        $results = [];

        foreach ($groups as $group) {
            $results[] = [
                ...$group['key'],
                ...array_map(function (array $plan) use ($group, &$notes): string {
                    return $this->compute($plan[0], $plan[1], $plan[2], $group['rows'], $notes);
                }, $plans),
            ];
        }

        return [[...array_map(fn (int $index): string => $csv->columns[$index], $groupIndexes), ...array_column($plans, 2)], $results];
    }

    /**
     * @param  list<list<string>>  $rows
     * @param  list<string>  $notes
     */
    private function compute(string $function, ?int $index, string $label, array $rows, array &$notes): string
    {
        if ($index === null) {
            return (string) count($rows);
        }

        $values = array_values(array_filter(array_map(fn (array $row): string => trim($row[$index]), $rows), fn (string $value): bool => $value !== ''));

        if ($function === 'count') {
            return (string) count($values);
        }

        $numbers = array_values(array_filter(array_map(self::number(...), $values)));

        if (in_array($function, ['min', 'max'], true) && count($numbers) !== count($values)) {
            usort($values, $this->compare(...));

            return $values === [] ? '' : ($function === 'min' ? $values[0] : $values[count($values) - 1]);
        }

        if (count($numbers) !== count($values)) {
            $skipped = count($values) - count($numbers);
            $note = "{$label}: {$skipped} ".($skipped === 1 ? "value isn't a number and was" : "values aren't numbers and were").' left out.';

            if (! in_array($note, $notes, true)) {
                $notes[] = $note;
            }
        }

        if ($numbers === []) {
            return '';
        }

        $result = match ($function) {
            'sum' => array_reduce($numbers, fn (BigDecimal $sum, BigDecimal $number): BigDecimal => $sum->plus($number), BigDecimal::zero()),
            'avg' => array_reduce($numbers, fn (BigDecimal $sum, BigDecimal $number): BigDecimal => $sum->plus($number), BigDecimal::zero())
                ->dividedBy(count($numbers), max(4, ...array_map(fn (BigDecimal $number): int => $number->getScale(), $numbers)), RoundingMode::HalfUp)
                ->strippedOfTrailingZeros(),
            'min' => BigDecimal::min(...$numbers),
            'max' => BigDecimal::max(...$numbers),
        };

        return (string) $result;
    }

    /**
     * @param  list<string>  $columns
     * @param  list<list<string>>  $rows
     * @param  list<array{column: string, direction?: string|null}>  $orderBy
     * @return list<list<string>>
     */
    private function sort(array $columns, array $rows, array $orderBy): array
    {
        if ($orderBy === []) {
            return $rows;
        }

        $keys = array_map(fn (array $order): array => [
            $this->column($columns, $order['column']),
            mb_strtolower((string) ($order['direction'] ?? 'asc')) === 'desc' ? -1 : 1,
        ], $orderBy);

        usort($rows, function (array $one, array $other) use ($keys): int {
            foreach ($keys as [$index, $direction]) {
                // Whichever way it is sorted, numbers stay together and blanks go last.
                $ranks = [$this->rank($one[$index]), $this->rank($other[$index])];

                if ($ranks[0] !== $ranks[1]) {
                    return $ranks[0] <=> $ranks[1];
                }

                $order = $this->compare($one[$index], $other[$index]);

                if ($order !== 0) {
                    return $order * $direction;
                }
            }

            return 0;
        });

        return $rows;
    }

    private function rank(string $value): int
    {
        return match (true) {
            trim($value) === '' => 2,
            self::number($value) !== null => 0,
            default => 1,
        };
    }

    /**
     * @param  list<string>  $columns
     *
     * @throws InvalidArgumentException
     */
    private function column(array $columns, string $name): int
    {
        $wanted = mb_strtolower(trim($name));

        foreach ($columns as $index => $column) {
            if (mb_strtolower($column) === $wanted) {
                return $index;
            }
        }

        throw new InvalidArgumentException("There is no column \"{$name}\". The columns are: ".implode(', ', $columns).'.');
    }
}
