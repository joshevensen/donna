<?php

namespace App\Ai\Tools;

use App\Enums\FileRole;
use App\Files\FileLibrary;
use App\Files\FileText;
use App\Memory\MemoryRenderer;
use App\Models\File;
use App\Models\Message;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;

class ListFiles implements Tool
{
    public function __construct(
        private Message $reply,
        private FileLibrary $library,
        private MemoryRenderer $renderer,
    ) {}

    public function name(): string
    {
        return 'list_files';
    }

    public function description(): string
    {
        return 'See what files there are: kept ones first, then working files used lately. With q, files whose name contains it, however old. '
            .'Use it for "show me my files" and "open the launch plan". The files it finds are shown to Josh as cards under your reply, '
            .'which he taps to open, so don\'t list them again in words.';
    }

    public function handle(Request $request): string
    {
        $input = $request->validate(['q' => ['nullable', 'string', 'max:200']]);

        $files = $this->library->listing($input['q'] ?? null);

        if ($files->isEmpty()) {
            return filled($input['q'] ?? null) ? "No file's name contains \"{$input['q']}\"." : 'There are no files yet.';
        }

        $cards = $files->take(config('donna.files.cards_per_reply'));

        $cards->each(fn (File $file) => $this->library->touch($file, $this->reply, FileRole::Opened));

        return $files->map(fn (File $file): string => "- file #{$file->id} \"{$file->name}\" — ".FileText::describe($file)
            .' — '.($file->isKept() ? 'kept' : 'working')
            .' — arrived '.$this->renderer->timestamp($file->created_at))->implode("\n")
            ."\n".($cards->count() < $files->count()
                ? "The first {$cards->count()} are shown to Josh as cards under your reply."
                : 'They are shown to Josh as cards under your reply.');
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'q' => $schema->string()->description('Part of a file name to look for. Leave out to list everything current.'),
        ];
    }
}
