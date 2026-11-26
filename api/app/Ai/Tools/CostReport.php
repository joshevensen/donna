<?php

namespace App\Ai\Tools;

use App\Costs\CostReport as Report;
use App\Enums\CostPeriod;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;

class CostReport implements Tool
{
    public function __construct(private Report $report) {}

    public function name(): string
    {
        return 'cost_report';
    }

    public function description(): string
    {
        return 'Get what you have cost Josh in model usage for a period, in US dollars, in total and by what the money went on. '
            .'The numbers are exact; report them as given.';
    }

    public function handle(Request $request): string
    {
        $input = $request->validate(['period' => ['required', Rule::enum(CostPeriod::class)]]);

        $report = $this->report->for(CostPeriod::from($input['period']));

        $lines = [
            sprintf(
                '%s (%s to %s): $%s across %s, including %s.',
                str_replace('_', ' ', $report['period']),
                $report['from'] ?? 'the beginning',
                $report['until'],
                $report['total_usd'],
                $this->count($report['calls'], 'model call'),
                $this->count($report['web_searches'], 'web search'),
            ),
        ];

        foreach ($report['by_purpose'] as $purpose => $row) {
            $lines[] = sprintf('- %s: $%s (%s)', $purpose, $row['usd'], $this->count($row['calls'], 'call'));
        }

        return implode("\n", $lines);
    }

    private function count(int $number, string $noun): string
    {
        return $number.' '.Str::plural($noun, $number);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'period' => $schema->string()->enum(CostPeriod::class)->required(),
        ];
    }
}
