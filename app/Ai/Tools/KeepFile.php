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
 * Keeping is reversible and stays inside Donna's own store, so she needs no
 * confirmation. The card under her reply carries the Kept badge, so a keep
 * she made on her own is visible even if Josh skims the reply.
 */
class KeepFile implements Tool
{
    public function __construct(
        private Message $reply,
        private FileFinder $files,
        private FileLibrary $library,
    ) {}

    public function name(): string
    {
        return 'keep_file';
    }

    public function description(): string
    {
        return 'Keep a file, so you find it on your own whenever its subject comes up, or stop keeping it (keep=false). '
            .'Keep what Josh gives you as context or reference: his notes, a plan, a vendor document, an export he will come back to. '
            .'Don\'t keep a file he only wants transformed, or a one-off you wrote. When you keep one on your own, say so in a line, so he can tell you to forget it. '
            .'If he stopped keeping a file, don\'t keep it again unless he asks. Images can\'t be kept.';
    }

    public function handle(Request $request): string
    {
        $input = $request->validate([
            'file' => ['required', 'string', 'max:255'],
            'keep' => ['required', 'boolean'],
        ]);

        $file = $this->files->find($input['file']);

        if (is_string($file)) {
            return $file;
        }

        $keep = (bool) $input['keep'];

        if ($keep && ! $file->kind->hasBody()) {
            return "{$file->name} is an image. Images aren't kept for search; you can look at one again with view_file whenever Josh asks.";
        }

        if ($keep === $file->isKept()) {
            return $keep ? "{$file->name} is already kept." : "{$file->name} isn't kept.";
        }

        $this->library->keep($file, $keep);
        $this->library->touch($file, $this->reply, FileRole::Opened);

        if (! $keep) {
            return "{$file->name} is no longer kept. The file itself is still there.";
        }

        return "Kept {$file->name}. Its card under your reply shows it. Say in a line that you kept it, so Josh can tell you to forget it."
            .(blank(trim((string) $file->body(), " \n\r\t\f")) ? ' It has no text, so you won\'t find it by what it says: link it to who or what it is about with link_file.' : '');
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'file' => $schema->string()->description('The file\'s number (12) or name.')->required(),
            'keep' => $schema->boolean()->description('True to keep it, false to stop keeping it.')->required(),
        ];
    }
}
