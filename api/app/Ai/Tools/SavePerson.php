<?php

namespace App\Ai\Tools;

use App\Memory\SubjectResolver;
use App\Models\Person;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;

class SavePerson implements Tool
{
    public function __construct(private SubjectResolver $subjects) {}

    public function name(): string
    {
        return 'save_person';
    }

    public function description(): string
    {
        return 'Add someone in Josh\'s life, or update who they are: their name, other names they go by, '
            .'how they relate to Josh, and a short living summary. Only the fields you pass are changed.';
    }

    public function handle(Request $request): string
    {
        $input = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'new_name' => ['nullable', 'string', 'max:255'],
            'aliases' => ['nullable', 'array'],
            'aliases.*' => ['string', 'max:255'],
            'relationship' => ['nullable', 'string', 'max:255'],
            'summary' => ['nullable', 'string', 'max:5000'],
        ]);

        $person = $this->subjects->person($input['name']);
        $created = $person === null;
        $person ??= new Person(['name' => trim($input['name'])]);

        $person->fill(array_filter([
            'name' => $input['new_name'] ?? null,
            'relationship' => $input['relationship'] ?? null,
            'summary' => $input['summary'] ?? null,
        ], filled(...)));

        if (! empty($input['aliases'])) {
            $person->aliases = collect([...$person->aliases, ...$input['aliases']])
                ->map(fn (string $alias): string => trim($alias))
                ->reject(fn (string $alias): bool => $alias === '' || $alias === $person->name)
                ->unique()
                ->values()
                ->all();
        }

        $person->save();

        return ($created ? 'Added' : 'Updated')." {$person->name} (person #{$person->id}).";
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'name' => $schema->string()->description('The name you know them by now.')->required(),
            'new_name' => $schema->string()->description('Only to rename someone.'),
            'aliases' => $schema->array()->items($schema->string())->description('Other names to add: nicknames, full names.'),
            'relationship' => $schema->string()->description('How they relate to Josh, e.g. "wife", "yarn vendor", "college friend".'),
            'summary' => $schema->string()->description('Who they are and where things stand with them. Replaces the old summary.'),
        ];
    }
}
