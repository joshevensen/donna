<?php

namespace App\Ai\Tools;

use App\Enums\ProjectStatus;
use App\Enums\ProjectTier;
use App\Memory\ProjectLinker;
use App\Memory\SubjectResolver;
use App\Models\Project;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Validation\Rule;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;

class ProposeProject implements Tool
{
    public function __construct(
        private SubjectResolver $subjects,
        private ProjectLinker $links,
    ) {}

    public function name(): string
    {
        return 'propose_project';
    }

    public function description(): string
    {
        return 'Record that something looks like a real project, so the question of tracking it is not lost. '
            .'Call it as you ask Josh whether to track it. It is not tracked until he says yes; then use update_project.';
    }

    public function handle(Request $request): string
    {
        $input = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'summary' => ['nullable', 'string', 'max:5000'],
            'owner' => ['nullable', 'string', 'max:255'],
            'tier' => ['nullable', Rule::enum(ProjectTier::class)],
        ]);

        $existing = $this->subjects->project($input['name']);

        if ($existing !== null) {
            return "You already have {$existing->name} (project #{$existing->id}, {$existing->status->value}).";
        }

        $project = Project::create([
            'name' => trim($input['name']),
            'summary' => $input['summary'] ?? null,
            'owner_person_id' => $this->subjects->person($input['owner'] ?? 'Josh', create: true)->id,
            'tier' => $input['tier'] ?? null,
            'status' => ProjectStatus::Proposed,
            'status_changed_at' => now(),
        ]);

        $this->links->linkProject($project);

        return "Saved as a proposal (project #{$project->id}). It is not tracked until Josh says yes.";
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'name' => $schema->string()->required(),
            'summary' => $schema->string()->description('What it is and why it looks like a project.'),
            'owner' => $schema->string()->description('Whose project it is. Defaults to Josh.'),
            'tier' => $schema->string()->enum(ProjectTier::class)->description('Only if it is already obvious.'),
        ];
    }
}
