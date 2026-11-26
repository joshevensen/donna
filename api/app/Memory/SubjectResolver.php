<?php

namespace App\Memory;

use App\Models\Person;
use App\Models\Project;
use App\Models\Topic;
use Illuminate\Database\Eloquent\Model;

/**
 * Finds what a fact is about by name. Matching is plain code: names and
 * aliases, case-insensitive.
 */
class SubjectResolver
{
    /**
     * Ways the model refers to Josh that are not his name.
     */
    private const array JOSH = ['me', 'myself', 'i', 'josh', 'user'];

    public function __construct(private Embedder $embedder) {}

    /**
     * Find a person by name or alias, creating them when asked to.
     */
    public function person(string $name, bool $create = false): ?Person
    {
        if (in_array(mb_strtolower(trim($name)), self::JOSH, true)) {
            return Person::josh();
        }

        $person = Person::query()->orderBy('id')->get()
            ->first(fn (Person $person): bool => $this->matches($name, $person->names()));

        return $person ?? ($create ? Person::create(['name' => trim($name)]) : null);
    }

    /**
     * Find a project by name or alias. Projects are never created in passing.
     */
    public function project(string $name): ?Project
    {
        return Project::query()->orderBy('id')->get()
            ->first(fn (Project $project): bool => $this->matches($name, $project->names()));
    }

    /**
     * Find a topic by name, creating and embedding it when asked to.
     */
    public function topic(string $name, bool $create = false, ?Model $trigger = null): ?Topic
    {
        $topic = Topic::query()->whereRaw('lower(name) = ?', [mb_strtolower(trim($name))])->first();

        return $topic ?? ($create ? Topic::create([
            'name' => trim($name),
            'embedding' => $this->embedder->embed(trim($name), $trigger),
        ]) : null);
    }

    /**
     * Resolve a list of named subjects. People and topics are created when
     * new; an unknown project is reported back rather than invented.
     *
     * @param  list<array{type: string, name: string}>  $subjects
     * @return array{0: list<Person|Project|Topic>, 1: list<string>} Resolved models, then the names that could not be linked.
     */
    public function resolve(array $subjects, ?Model $trigger = null): array
    {
        $resolved = [];
        $missing = [];

        foreach ($subjects as $subject) {
            $model = match ($subject['type']) {
                'person' => $this->person($subject['name'], create: true),
                'project' => $this->project($subject['name']),
                'topic' => $this->topic($subject['name'], create: true, trigger: $trigger),
                default => null,
            };

            if ($model === null) {
                $missing[] = $subject['name'];
            } else {
                $resolved[$model->getMorphClass().':'.$model->getKey()] = $model;
            }
        }

        return [array_values($resolved), $missing];
    }

    /**
     * @param  list<string>  $names
     */
    private function matches(string $name, array $names): bool
    {
        return collect($names)->contains(fn (string $candidate): bool => NameMatcher::same($name, $candidate));
    }
}
