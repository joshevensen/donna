<?php

namespace App\Ai\Tools;

use App\Memory\FactWriter;
use App\Models\Fact;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;

class ForgetFact implements Tool
{
    public function __construct(private FactWriter $facts) {}

    public function name(): string
    {
        return 'forget_fact';
    }

    public function description(): string
    {
        return 'Permanently delete a fact, and every earlier version of it. Only when Josh tells you to forget it. '
            .'If something is merely wrong, use correct_fact instead.';
    }

    public function handle(Request $request): string
    {
        $input = $request->validate(['fact_id' => ['required', 'integer']]);

        $fact = Fact::query()->find($input['fact_id']);

        if ($fact === null) {
            return "There is no fact #{$input['fact_id']}. It may already be forgotten.";
        }

        $deleted = $this->facts->forget($fact);

        return "Forgotten: fact #{$fact->id}".($deleted > 1 ? ' and '.($deleted - 1).' earlier version(s) of it' : '').'.';
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'fact_id' => $schema->integer()->description('The number of the fact to forget, as shown in memory.')->required(),
        ];
    }
}
