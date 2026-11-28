<?php

namespace App\Files;

use App\Models\File;
use App\Models\FileChunk;

/**
 * Keeps a file's chunks matching its current text. Only kept files have
 * chunks, so this also removes them when a file stops being kept or is
 * deleted. It touches the database only: the vectors are filled in by the
 * IndexFile job, and until then the chunks are found by full-text search.
 */
class FileIndexer
{
    public function __construct(private Chunker $chunker) {}

    /**
     * Rebuild the chunks from the current version. A chunk whose text hasn't
     * changed keeps its row, and with it its embedding.
     */
    public function sync(File $file): void
    {
        $body = $file->isKept() && ! $file->trashed() ? $file->currentVersion()->value('body') : null;

        if ($body === null) {
            $file->chunks()->delete();

            return;
        }

        $existing = $file->chunks()->get(['id', 'position', 'content_hash'])->groupBy('content_hash');

        foreach ($this->chunker->chunk($file->kind, $body) as $position => $piece) {
            $hash = hash('sha256', $piece['heading_path']."\n".$piece['body']);

            /** @var FileChunk|null $same */
            $same = $existing->get($hash)?->shift();

            if ($same === null) {
                $file->chunks()->create([...$piece, 'position' => $position, 'content_hash' => $hash]);
            } elseif ($same->position !== $position) {
                $same->update(['position' => $position]);
            }
        }

        FileChunk::query()->whereKey($existing->flatten()->pluck('id'))->delete();
    }
}
