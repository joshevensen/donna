<?php

namespace App\Ai\Tools;

use App\Enums\InterviewAreaStatus;
use App\Memory\NameMatcher;
use App\Models\InterviewArea;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Validation\Rule;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;

class UpdateInterview implements Tool
{
    public function name(): string
    {
        return 'update_interview';
    }

    public function description(): string
    {
        return 'Track progress on getting to know Josh: mark an interview area in progress or covered, '
            .'with notes on what has been covered and what is still open, so you can pick up where you left off.';
    }

    public function handle(Request $request): string
    {
        $input = $request->validate([
            'area' => ['required', 'string', 'max:255'],
            'status' => ['required', Rule::enum(InterviewAreaStatus::class)],
            'notes' => ['nullable', 'string', 'max:5000'],
        ]);

        $areas = InterviewArea::query()->orderBy('id')->get();
        $area = $areas->first(fn (InterviewArea $area): bool => NameMatcher::same($area->area, $input['area']));

        if ($area === null) {
            return "There is no interview area called {$input['area']}. The areas are: ".$areas->implode('area', ', ').'.';
        }

        $area->update(array_filter([
            'status' => $input['status'],
            'notes' => $input['notes'] ?? null,
            'last_touched_at' => now(),
        ], filled(...)));

        return "{$area->area} is now {$area->status->value}.";
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'area' => $schema->string()->description('The area\'s name, exactly as listed in memory.')->required(),
            'status' => $schema->string()->enum(InterviewAreaStatus::class)->required(),
            'notes' => $schema->string()->description('What has been covered and what is still open. Replaces the old notes.'),
        ];
    }
}
