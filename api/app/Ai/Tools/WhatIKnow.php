<?php

namespace App\Ai\Tools;

use App\Memory\MemoryRenderer;
use App\Memory\SubjectResolver;
use App\Models\Fact;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Validation\Rule;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;

class WhatIKnow implements Tool
{
    public function __construct(
        private SubjectResolver $subjects,
        private MemoryRenderer $renderer,
    ) {}

    public function name(): string
    {
        return 'what_i_know';
    }

    public function description(): string
    {
        return 'List everything you currently know about one person, project, or topic. '
            .'Use when Josh asks what you know about himself, someone, or something.';
    }

    public function handle(Request $request): string
    {
        $input = $request->validate([
            'type' => ['required', Rule::in(['person', 'project', 'topic'])],
            'name' => ['required', 'string', 'max:255'],
        ]);

        $subject = match ($input['type']) {
            'person' => $this->subjects->person($input['name']),
            'project' => $this->subjects->project($input['name']),
            'topic' => $this->subjects->topic($input['name']),
        };

        if ($subject === null) {
            return "You have no {$input['type']} called {$input['name']}.";
        }

        $limit = config('donna.context.what_i_know_limit');
        $total = $subject->facts()->active()->count();

        $facts = $subject->facts()
            ->with(['people', 'projects', 'topics', 'predecessors'])
            ->active()
            ->latest('facts.id')
            ->limit($limit)
            ->get();

        return implode("\n", array_filter([
            "{$subject->name}".($subject->summary !== null ? ": {$subject->summary}" : ''),
            $facts->isEmpty() ? 'No facts saved yet.' : null,
            ...$facts->map(fn (Fact $fact): string => $this->renderer->fact($fact)),
            $total > $limit ? 'Those are the newest '.$limit.' of '.$total.' facts. Use recall to look for anything older.' : null,
        ]));
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'type' => $schema->string()->enum(['person', 'project', 'topic'])->required(),
            'name' => $schema->string()->description('Their name. Use "Josh" for Josh himself.')->required(),
        ];
    }
}
