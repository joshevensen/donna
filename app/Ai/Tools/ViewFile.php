<?php

namespace App\Ai\Tools;

use App\Files\FileFinder;
use App\Files\Looks;
use App\Files\ModelFile;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;

/**
 * A tool can only answer in words, so it can't hand Donna a picture. This
 * one notes that she asked; when her current call finishes, the reply job
 * makes one more with the file attached, and that call writes the reply.
 */
class ViewFile implements Tool
{
    public function __construct(
        private Looks $looks,
        private FileFinder $files,
        private ModelFile $shown,
    ) {}

    public function name(): string
    {
        return 'view_file';
    }

    public function description(): string
    {
        return 'Look at an image or a PDF\'s pages again, e.g. "look at that screenshot again". You only see a picture in the turn it arrives; '
            .'afterwards you have to look. You will be shown it in a moment and can answer then, so write nothing about it yet. '
            .'At most '.config('donna.files.looks_per_reply').' looks in one reply. For a file with text, use read_file instead.';
    }

    public function handle(Request $request): string
    {
        $input = $request->validate([
            'file' => ['required', 'string', 'max:255'],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);

        $file = $this->files->find($input['file']);

        if (is_string($file)) {
            return $file;
        }

        $cannot = $this->shown->cannotShow($file);

        if ($cannot !== null) {
            return $cannot;
        }

        if (! $this->looks->ask($file, $input['page'] ?? null)) {
            return 'You have already looked as many times as one reply allows. Answer from what you have seen, and offer to look at the rest next.';
        }

        return "You will be shown {$file->name} in a moment, and will answer then. Say nothing about what is in it yet.";
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'file' => $schema->string()->description('The image\'s or PDF\'s number (12) or name.')->required(),
            'page' => $schema->integer()->description('For a PDF: the page you most need to see.'),
        ];
    }
}
