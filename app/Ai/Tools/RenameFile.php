<?php

namespace App\Ai\Tools;

use App\Enums\FileRole;
use App\Files\FileFinder;
use App\Files\FileLibrary;
use App\Models\Message;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;

class RenameFile implements Tool
{
    public function __construct(
        private Message $reply,
        private FileFinder $files,
        private FileLibrary $library,
    ) {}

    public function name(): string
    {
        return 'rename_file';
    }

    public function description(): string
    {
        return 'Give a file a new name.';
    }

    public function handle(Request $request): string
    {
        $input = $request->validate([
            'file' => ['required', 'string', 'max:255'],
            'name' => ['required', 'string', 'max:200', 'not_regex:#[/\\\\\x00-\x1F]#'],
        ]);

        $file = $this->files->find($input['file']);

        if (is_string($file)) {
            return $file;
        }

        $old = $file->name;

        $this->library->rename($file, trim($input['name']));
        $this->library->touch($file, $this->reply, FileRole::Opened);

        return "Renamed \"{$old}\" to \"{$file->name}\" (still file #{$file->id}).";
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'file' => $schema->string()->description('The file\'s number (12) or current name.')->required(),
            'name' => $schema->string()->description('The new name, with its extension.')->required(),
        ];
    }
}
