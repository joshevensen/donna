<?php

namespace App\Jobs;

use App\Files\FileIndexer;
use App\Memory\Embedder;
use App\Models\File;
use App\Models\FileChunk;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Middleware\WithoutOverlapping;

/**
 * Indexes a kept file for Archive Memory: brings its chunks up to date and
 * embeds the ones that have no vector yet, so an edit only re-embeds what
 * changed. If embeddings are down the chunks stay findable by full-text
 * search and EmbedMissing fills the vectors in later.
 */
class IndexFile implements ShouldQueue
{
    use Queueable;

    public int $tries = 4;

    /**
     * @var list<int>
     */
    public array $backoff = [10, 60, 300];

    /**
     * @param  int  $fileId  By ID, so a file deleted before this runs is simply skipped.
     */
    public function __construct(public int $fileId) {}

    /**
     * Two edits in a row must not index the same file at once.
     *
     * @return list<object>
     */
    public function middleware(): array
    {
        return [(new WithoutOverlapping("index-file:{$this->fileId}"))->releaseAfter(10)->expireAfter(600)];
    }

    public function handle(FileIndexer $indexer, Embedder $embedder): void
    {
        $file = File::withTrashed()->find($this->fileId);

        if ($file === null) {
            return;
        }

        $indexer->sync($file);

        $file->chunks()
            ->whereNull('embedding')
            ->orderBy('position')
            ->get()
            ->chunk(config('donna.files.embed_batch'))
            ->each(function ($chunks) use ($embedder, $file): void {
                $embeddings = $embedder->attemptMany(
                    $chunks->map(fn (FileChunk $chunk): string => FileChunk::textToEmbed($file->name, $chunk->heading_path, $chunk->body))->values()->all(),
                    $file,
                );

                foreach ($chunks->values() as $index => $chunk) {
                    if ($embeddings[$index] !== null) {
                        $chunk->update(['embedding' => $embeddings[$index]]);
                    }
                }
            });
    }
}
