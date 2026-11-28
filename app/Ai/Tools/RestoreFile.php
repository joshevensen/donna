<?php

namespace App\Ai\Tools;

use App\Enums\FileRole;
use App\Files\FileFinder;
use App\Files\FileLibrary;
use App\Models\Message;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;

class RestoreFile implements Tool
{
    public function __construct(
        private Message $reply,
        private FileFinder $files,
        private FileLibrary $library,
    ) {}

    public function name(): string
    {
        return 'restore_file';
    }

    public function description(): string
    {
        return 'Bring back a file that was deleted, as it was. If it was kept, it is kept again.';
    }

    public function handle(Request $request): string
    {
        $input = $request->validate(['file' => ['required', 'string', 'max:255']]);

        $file = $this->files->find($input['file'], deleted: true);

        if (is_string($file)) {
            return $file;
        }

        $this->library->restore($file);
        $this->library->touch($file, $this->reply, FileRole::Opened);

        return "\"{$file->name}\" (file #{$file->id}) is back".($file->isKept() ? ', and kept as before.' : '.');
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'file' => $schema->string()->description('The deleted file\'s number (12) or name.')->required(),
        ];
    }
}
