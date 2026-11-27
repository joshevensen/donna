<?php

namespace App\Ai\Tools;

use App\Enums\FactProvenance;
use App\Memory\FactWriter;
use App\Models\Fact;
use App\Models\Message;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;

class CorrectFact implements Tool
{
    public function __construct(
        private Message $message,
        private FactWriter $facts,
    ) {}

    public function name(): string
    {
        return 'correct_fact';
    }

    public function description(): string
    {
        return 'Replace a fact that is wrong or out of date with a corrected statement. '
            .'The old version is kept as history, so you still know what Josh used to think.';
    }

    public function handle(Request $request): string
    {
        $input = $request->validate([
            'fact_id' => ['required', 'integer'],
            'statement' => ['required', 'string', 'max:2000'],
            'notable_change' => ['nullable', 'boolean'],
        ]);

        $fact = Fact::query()->find($input['fact_id']);

        if ($fact === null) {
            return "There is no fact #{$input['fact_id']}.";
        }

        if (! $fact->isActive()) {
            return "Fact #{$fact->id} was already replaced".($fact->superseded_by_id !== null ? " by fact #{$fact->superseded_by_id}" : '').'. Correct that one instead.';
        }

        $new = $this->facts->supersede(
            old: $fact,
            statement: $input['statement'],
            provenance: FactProvenance::Correction,
            notableChange: (bool) ($input['notable_change'] ?? false),
            source: $this->message,
        );

        return "Fact #{$fact->id} is now history. The current version is fact #{$new->id}.";
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'fact_id' => $schema->integer()->description('The number of the fact to replace, as shown in memory (#12 is 12).')->required(),
            'statement' => $schema->string()->description('The corrected fact, as one self-contained sentence.')->required(),
            'notable_change' => $schema->boolean()->description('True when Josh changing his mind on this is worth raising later.'),
        ];
    }
}
