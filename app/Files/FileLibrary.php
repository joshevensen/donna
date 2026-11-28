<?php

namespace App\Files;

use App\Enums\FileRole;
use App\Jobs\IndexFile;
use App\Models\File;
use App\Models\Message;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

/**
 * What can happen to a file besides its text changing: keeping, renaming,
 * deleting and bringing back, and being tied to a message. Josh's switch in
 * the viewer and Donna's tools both come through here, so they can't differ.
 */
class FileLibrary
{
    public function __construct(private FileIndexer $indexer) {}

    /**
     * What "my files" means: kept files first, then the working files used
     * lately. With a search, any file whose name contains it, however old.
     * Files that were uploaded but never sent with a message aren't listed.
     *
     * @return Collection<int, File>
     */
    public function listing(?string $search = null): Collection
    {
        $search = trim((string) $search);

        return File::query()
            ->inUse()
            ->with('currentVersion:id,file_id,number,author')
            ->when($search !== '', fn (Builder $query) => $query->whereLike('name', '%'.addcslashes($search, '%_\\').'%'))
            ->when($search === '', fn (Builder $query) => $query->where(fn (Builder $query) => $query
                ->whereNotNull('kept_at')
                ->orWhere('updated_at', '>=', now()->subDays(config('donna.files.recent_days')))))
            ->orderByRaw('kept_at is null')
            ->latest('updated_at')
            ->latest('id')
            ->limit(config('donna.files.list_limit'))
            ->get();
    }

    /**
     * How many files there are of each sort, for the line in Donna's memory.
     *
     * @return array{kept: int, working: int}
     */
    public function counts(): array
    {
        return [
            'kept' => File::query()->kept()->count(),
            'working' => File::query()->working()->inUse()->count(),
        ];
    }

    /**
     * Keep the file, so Archive Memory finds it, or stop keeping it. Its
     * chunks are written or removed with the change; the file itself stays
     * either way.
     */
    public function keep(File $file, bool $keep): void
    {
        if ($keep === $file->isKept()) {
            return;
        }

        DB::transaction(function () use ($file, $keep): void {
            $meta = $file->meta ?? [];

            // Remembered so Donna doesn't keep a file again that Josh has said to forget.
            if ($keep) {
                unset($meta['unkept_at']);
            } else {
                $meta['unkept_at'] = now()->toIso8601String();
            }

            $file->update(['kept_at' => $keep ? now() : null, 'meta' => $meta]);

            $this->indexer->sync($file);
        });

        if ($keep) {
            IndexFile::dispatch($file->id)->afterCommit();
        }
    }

    /**
     * A chunk is embedded with its file's name, so a kept file is embedded again.
     */
    public function rename(File $file, string $name): void
    {
        if ($name === $file->name) {
            return;
        }

        DB::transaction(function () use ($file, $name): void {
            $file->update(['name' => $name]);
            $file->chunks()->update(['embedding' => null]);
        });

        if ($file->isKept()) {
            IndexFile::dispatch($file->id)->afterCommit();
        }
    }

    /**
     * Soft delete: the file stops being found and listed, and can be brought back.
     */
    public function delete(File $file): void
    {
        DB::transaction(function () use ($file): void {
            $file->delete();

            $this->indexer->sync($file);
        });
    }

    public function restore(File $file): void
    {
        DB::transaction(function () use ($file): void {
            $file->restore();

            $this->indexer->sync($file);
        });

        if ($file->isKept()) {
            IndexFile::dispatch($file->id)->afterCommit();
        }
    }

    /**
     * Note what a message had to do with a file. Safe to repeat.
     */
    public function touch(File $file, Message $message, FileRole $role): void
    {
        DB::table('file_message')->insertOrIgnore([
            'file_id' => $file->id,
            'message_id' => $message->id,
            'role' => $role->value,
        ]);
    }
}
