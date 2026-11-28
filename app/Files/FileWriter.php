<?php

namespace App\Files;

use App\Enums\MessageAuthor;
use App\Jobs\IndexFile;
use App\Models\File;
use App\Models\FileVersion;
use App\Models\Message;
use Illuminate\Support\Facades\DB;

/**
 * The one place a file's text changes. Every change is a new version; the
 * file, its version and, for a kept file, its chunks all change in one
 * transaction.
 */
class FileWriter
{
    public function __construct(private FileIndexer $indexer) {}

    /**
     * Write the file's next version.
     *
     * @param  string  $summary  What changed, in words, written by code.
     * @param  Message|null  $reply  The reply Donna made the change in.
     * @param  string|null  $operationHash  Identifies the change within that reply, so a retried reply can skip it.
     */
    public function write(
        File $file,
        string $body,
        MessageAuthor $author,
        string $summary,
        ?Message $reply = null,
        ?string $operationHash = null,
    ): FileVersion {
        $version = DB::transaction(function () use ($file, $body, $author, $summary, $reply, $operationHash): FileVersion {
            // Locked, so two writers can't both take the same version number.
            $locked = File::withTrashed()->lockForUpdate()->findOrFail($file->id);

            $version = $locked->versions()->create([
                'number' => ((int) $locked->versions()->max('number')) + 1,
                'body' => $body,
                'content_hash' => hash('sha256', $body),
                'author' => $author,
                'message_id' => $reply?->id,
                'operation_hash' => $operationHash,
                'summary' => $summary,
                'created_at' => now(),
            ]);

            $locked->update([
                'current_version_id' => $version->id,
                // A text file's size is the size of what it now holds; a PDF's stays its original's.
                ...($locked->kind->isStored() ? [] : ['size_bytes' => strlen($body)]),
            ]);

            $file->setRawAttributes($locked->getAttributes(), sync: true);
            $file->setRelation('currentVersion', $version);

            if ($file->isKept()) {
                $this->indexer->sync($file);
            }

            return $version;
        });

        if ($file->isKept()) {
            IndexFile::dispatch($file->id)->afterCommit();
        }

        return $version;
    }
}
