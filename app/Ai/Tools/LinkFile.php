<?php

namespace App\Ai\Tools;

use App\Files\FileFinder;
use App\Memory\SubjectResolver;
use App\Models\Message;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;

class LinkFile implements Tool
{
    /**
     * @param  Message  $message  Josh's message, which a newly named topic's embedding is charged to.
     */
    public function __construct(
        private Message $message,
        private FileFinder $files,
        private SubjectResolver $subjects,
    ) {}

    public function name(): string
    {
        return 'link_file';
    }

    public function description(): string
    {
        return 'Say who or what a file is about: people, projects or topics. A linked file comes up with them. '
            .'Use it when Josh says so ("that\'s the Houston vendor PDF") or it is plainly about one. Set unlink=true to take a link away.';
    }

    public function handle(Request $request): string
    {
        $input = $request->validate([
            'file' => ['required', 'string', 'max:255'],
            'subjects' => ['required', 'array', 'min:1'],
            'subjects.*.type' => ['required', Rule::in(['person', 'project', 'topic'])],
            'subjects.*.name' => ['required', 'string', 'max:255'],
            'unlink' => ['nullable', 'boolean'],
        ]);

        $file = $this->files->find($input['file']);

        if (is_string($file)) {
            return $file;
        }

        if ($input['unlink'] ?? false) {
            $removed = collect($input['subjects'])
                ->map(fn (array $subject): ?Model => match ($subject['type']) {
                    'person' => $this->subjects->person($subject['name']),
                    'project' => $this->subjects->project($subject['name']),
                    default => $this->subjects->topic($subject['name']),
                })
                ->filter()
                ->each(fn (Model $subject) => DB::table('file_subjects')
                    ->where(['file_id' => $file->id, 'subject_type' => $subject->getMorphClass(), 'subject_id' => $subject->getKey()])
                    ->delete());

            return $removed->isEmpty()
                ? "{$file->name} wasn't linked to any of those."
                : "{$file->name} is no longer linked to ".$removed->pluck('name')->implode(', ').'.';
        }

        [$subjects, $missing] = $this->subjects->resolve($input['subjects'], $this->message);

        foreach ($subjects as $subject) {
            DB::table('file_subjects')->insertOrIgnore([
                'file_id' => $file->id,
                'subject_type' => $subject->getMorphClass(),
                'subject_id' => $subject->getKey(),
            ]);
        }

        return ($subjects === [] ? "{$file->name} wasn't linked to anything." : "{$file->name} is linked to ".collect($subjects)->pluck('name')->implode(', ').'.')
            .($missing === [] ? '' : ' Not linked to '.implode(', ', $missing).': no such project is tracked.');
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'file' => $schema->string()->description('The file\'s number (12) or name.')->required(),
            'subjects' => $schema->array()->items($schema->object([
                'type' => $schema->string()->enum(['person', 'project', 'topic'])->required(),
                'name' => $schema->string()->required(),
            ]))->description('Who or what the file is about.')->required(),
            'unlink' => $schema->boolean()->description('True to remove these links instead of adding them.'),
        ];
    }
}
