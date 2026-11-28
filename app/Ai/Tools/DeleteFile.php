<?php

namespace App\Ai\Tools;

use App\Enums\FileRole;
use App\Files\FileFinder;
use App\Files\FileLibrary;
use App\Models\Message;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;

/**
 * There is no delete button in the app: Josh asks Donna. The delete is
 * soft, so she can bring the file back if he changes his mind.
 */
class DeleteFile implements Tool
{
    public function __construct(
        private Message $reply,
        private FileFinder $files,
        private FileLibrary $library,
    ) {}

    public function name(): string
    {
        return 'delete_file';
    }

    public function description(): string
    {
        return 'Delete a file. Only when Josh asks you to, never on your own judgment. It can be brought back with restore_file.';
    }

    public function handle(Request $request): string
    {
        $input = $request->validate(['file' => ['required', 'string', 'max:255']]);

        $file = $this->files->find($input['file']);

        if (is_string($file)) {
            return $file;
        }

        $this->library->delete($file);
        $this->library->touch($file, $this->reply, FileRole::Opened);

        return "Deleted \"{$file->name}\" (file #{$file->id}). You can bring it back with restore_file if Josh changes his mind.";
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'file' => $schema->string()->description('The file\'s number (12) or name.')->required(),
        ];
    }
}
