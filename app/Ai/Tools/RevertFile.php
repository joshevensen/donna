<?php

namespace App\Ai\Tools;

use App\Files\FileEditor;
use App\Files\FileFinder;
use App\Models\Message;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;

class RevertFile implements Tool
{
    public function __construct(
        private Message $reply,
        private FileFinder $files,
        private FileEditor $editor,
    ) {}

    public function name(): string
    {
        return 'revert_file';
    }

    public function description(): string
    {
        return 'Put a file back the way it was at an earlier version. With no version, it goes back to how it was before your last edit: '
            .'that is what "undo that" means. Nothing is lost: the text in between stays in the file\'s history. Version 1 is always the original.';
    }

    public function handle(Request $request): string
    {
        $input = $request->validate([
            'file' => ['required', 'string', 'max:255'],
            'version' => ['nullable', 'integer', 'min:1'],
        ]);

        $file = $this->files->find($input['file']);

        return is_string($file) ? $file : $this->editor->revert($file, $this->reply, $input['version'] ?? null);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'file' => $schema->string()->description('The file\'s number (12) or name.')->required(),
            'version' => $schema->integer()->description('The version to go back to. Leave out to undo your last edit.'),
        ];
    }
}
