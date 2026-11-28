<?php

namespace App\Ai\Tools;

use App\Enums\FileKind;
use App\Files\Csv;
use App\Files\CsvQuery;
use App\Files\FileFinder;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Validation\Rule;
use InvalidArgumentException;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Throwable;

/**
 * Totals, counts and filtered rows from a CSV, worked out in PHP. Donna
 * chooses the question; the arithmetic is never hers.
 */
class QueryCsv implements Tool
{
    public function __construct(
        private FileFinder $files,
        private CsvQuery $query,
    ) {}

    public function name(): string
    {
        return 'query_csv';
    }

    public function description(): string
    {
        return 'Ask a CSV a question and get the exact answer: filter rows (where), group them (group_by), '
            .'count, sum, average, min or max a column (aggregate), sort (order_by) and limit. '
            .'Use it for every total, count or comparison about a CSV: never add or count rows yourself, even when you can see them. '
            .'Call it with only the file to get the columns, the row count and the first rows, so you can plan a query.';
    }

    public function handle(Request $request): string
    {
        $input = $request->validate([
            'file' => ['required', 'string', 'max:255'],
            'where' => ['nullable', 'array'],
            'where.*.column' => ['required', 'string'],
            'where.*.op' => ['required', 'string'],
            'where.*.value' => ['nullable'],
            'where.*.values' => ['nullable', 'array'],
            'group_by' => ['nullable', 'array'],
            'group_by.*' => ['string'],
            'aggregate' => ['nullable', 'array'],
            'aggregate.*.fn' => ['required', 'string'],
            'aggregate.*.column' => ['nullable', 'string'],
            'order_by' => ['nullable', 'array'],
            'order_by.*.column' => ['required', 'string'],
            'order_by.*.direction' => ['nullable', Rule::in(['asc', 'desc'])],
            'limit' => ['nullable', 'integer', 'min:1'],
        ]);

        $file = $this->files->find($input['file']);

        if (is_string($file)) {
            return $file;
        }

        if ($file->kind !== FileKind::Csv) {
            return "{$file->name} is a {$file->kind->label()} file, not a CSV. Use read_file.";
        }

        $csv = Csv::parse($file->body() ?? '');
        $about = "{$file->name}: ".number_format($csv->totalRows).' rows. Columns: '.implode(', ', $csv->columns).'.';
        $asked = array_filter(array_intersect_key($input, array_flip(['where', 'group_by', 'aggregate', 'order_by'])));
        $limit = min($input['limit'] ?? config('donna.files.query_rows'), config('donna.files.query_rows_max'));

        try {
            $result = $this->query->run($csv, $asked, $asked === [] && ! isset($input['limit']) ? config('donna.files.outline_rows') : $limit);
        } catch (InvalidArgumentException $exception) {
            return $exception->getMessage();
        } catch (Throwable $exception) {
            // A query she can reword is not worth losing the whole reply over.
            report($exception);

            return 'That query could not be run as written. Check the columns and values and try a simpler one.';
        }

        $shown = count($result['rows']);

        return implode("\n", array_filter([
            $about,
            isset($asked['where']) ? number_format($result['matched']).' '.($result['matched'] === 1 ? 'row matches' : 'rows match').' the filter.' : null,
            $shown === 0 ? 'No rows to show.' : implode(',', array_map($this->cell(...), $result['columns'])),
            ...array_map(fn (array $row): string => implode(',', array_map($this->cell(...), $row)), $result['rows']),
            $shown < $result['total'] ? "({$shown} of ".number_format($result['total']).' rows shown. Narrow the query, or raise limit up to '.config('donna.files.query_rows_max').'.)' : null,
            ...$result['notes'],
        ]));
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'file' => $schema->string()->description('The CSV\'s number (12) or name.')->required(),
            'where' => $schema->array()->items($schema->object([
                'column' => $schema->string()->required(),
                'op' => $schema->string()->enum(CsvQuery::OPERATORS)->required(),
                'value' => $schema->string()->description('What to compare with. Numbers, money and dates are compared as such.'),
                'values' => $schema->array()->items($schema->string())->description('For "in" only: the list of values to match any of.'),
            ]))->description('Conditions a row must meet; all of them.'),
            'group_by' => $schema->array()->items($schema->string())->description('Columns to group by.'),
            'aggregate' => $schema->array()->items($schema->object([
                'fn' => $schema->string()->enum(CsvQuery::FUNCTIONS)->required(),
                'column' => $schema->string()->description('The column to compute over. Not needed for a plain count of rows.'),
            ]))->description('What to compute, over all matching rows or per group.'),
            'order_by' => $schema->array()->items($schema->object([
                'column' => $schema->string()->description('A column, or a computed one by its label, e.g. "sum(Amount)" or "count".')->required(),
                'direction' => $schema->string()->enum(['asc', 'desc']),
            ])),
            'limit' => $schema->integer()->description('How many result rows to return. Defaults to 50.'),
        ];
    }

    private function cell(string $value): string
    {
        return preg_match('/[",\r\n]/', $value) === 1 ? '"'.str_replace('"', '""', $value).'"' : $value;
    }
}
