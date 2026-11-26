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

class UpdateProject implements Tool
{
    public function __construct(
        private SubjectResolver $subjects,
        private ProjectLinker $links,
    ) {}

    public function name(): string
    {
        return 'update_project';
    }

    public function description(): string
    {
        return 'Set a project\'s priority, status, owner, name, or summary once Josh has said so — including "yes, track it" '
            .'for one you proposed (status active). If Josh tells you outright about a project you have not seen, this creates it. '
            .'Only the fields you pass are changed.';
    }

    public function handle(Request $request): string
    {
        $input = $request->validate([
            'project' => ['required', 'string', 'max:255'],
            'new_name' => ['nullable', 'string', 'max:255'],
            'aliases' => ['nullable', 'array'],
            'aliases.*' => ['string', 'max:255'],
            'tier' => ['nullable', Rule::enum(ProjectTier::class)],
            'status' => ['nullable', Rule::in(['active', 'parked', 'dropped'])],
            'owner' => ['nullable', 'string', 'max:255'],
            'summary' => ['nullable', 'string', 'max:5000'],
        ]);

        $project = $this->subjects->project($input['project']);
        $created = $project === null;

        $project ??= new Project([
            'name' => trim($input['project']),
            'status' => ProjectStatus::Active,
            'owner_person_id' => $this->subjects->person('Josh')->id,
        ]);

        $project->fill(array_filter([
            'name' => $input['new_name'] ?? null,
            'tier' => $input['tier'] ?? null,
            'status' => $input['status'] ?? null,
            'summary' => $input['summary'] ?? null,
            'owner_person_id' => isset($input['owner']) ? $this->subjects->person($input['owner'], create: true)->id : null,
        ], filled(...)));

        if (! empty($input['aliases'])) {
            $project->aliases = collect([...$project->aliases, ...$input['aliases']])->unique()->values()->all();
        }

        if ($created || $project->isDirty('status')) {
            $project->status_changed_at = now();
        }

        $project->save();
        $project->load('owner');

        $this->links->linkProject($project);

        return ($created ? 'Now tracking' : 'Updated')." {$project->name} (project #{$project->id}): "
            .implode(', ', array_filter([$project->tier?->value, $project->status->value]))
            .", owned by {$project->owner->name}.";
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'project' => $schema->string()->description('The project\'s current name.')->required(),
            'new_name' => $schema->string()->description('Only to rename it.'),
            'aliases' => $schema->array()->items($schema->string())->description('Other names it goes by, to add.'),
            'tier' => $schema->string()->enum(ProjectTier::class)->description('main: brings in money and gets the focus. secondary: aligned. fun: how he decompresses.'),
            'status' => $schema->string()->enum(['active', 'parked', 'dropped']),
            'owner' => $schema->string()->description('Whose project it is, e.g. "Josh" or "Kristen".'),
            'summary' => $schema->string()->description('What it is and where it stands. Replaces the old summary.'),
        ];
    }
}
