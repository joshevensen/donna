<?php

namespace App\Ai\Tools;

use App\Costs\CostReport as Report;
use App\Enums\CostPeriod;
use Illuminate\Contracts\JsonSchema\JsonSchema;
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
                '%s (%s to %s): $%s across %d model calls, including %d web searches.',
                str_replace('_', ' ', $report['period']),
                $report['from'] ?? 'the beginning',
                $report['until'],
                $report['total_usd'],
                $report['calls'],
                $report['web_searches'],
            ),
        ];

        foreach ($report['by_purpose'] as $purpose => $row) {
            $lines[] = sprintf('- %s: $%s (%d calls)', $purpose, $row['usd'], $row['calls']);
        }

        return implode("\n", $lines);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'period' => $schema->string()->enum(CostPeriod::class)->required(),
        ];
    }
}
