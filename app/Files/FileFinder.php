<?php

namespace App\Files;

use App\Models\File;
use Illuminate\Database\Eloquent\Builder;

/**
 * Works out which file Donna means from what she passed a tool: a number
 * ("12", "#12", "file #12") or a name. Matching is plain code, and when it
 * can't settle on one file it says why instead of guessing.
 */
class FileFinder
{
    /**
     * @param  bool  $deleted  Look among deleted files, for bringing one back.
     * @return File|string The file, or a sentence for Donna saying why there isn't one.
     */
    public function find(string $reference, bool $deleted = false): File|string
    {
        $reference = trim($reference);
        $query = fn (): Builder => $deleted ? File::onlyTrashed() : File::query();

        if (preg_match('/^(?:file\s*)?#?(\d+)$/i', $reference, $match) === 1) {
            return $query()->find((int) $match[1]) ?? $this->missing((int) $match[1], $deleted);
        }

        $named = $query()->whereRaw('lower(name) = ?', [mb_strtolower($reference)])->latest('id')->get();

        if ($named->isEmpty()) {
            $named = $query()->whereLike('name', '%'.addcslashes($reference, '%_\\').'%')->latest('id')->limit(6)->get();
        }

        return match (true) {
            $named->isEmpty() => 'There is no '.($deleted ? 'deleted ' : '')."file called \"{$reference}\". Use list_files to see what there is.",
            $named->count() > 1 => 'More than one file matches "'.$reference.'": '
                .$named->map(fn (File $file): string => "file #{$file->id} \"{$file->name}\"")->implode(', ').'. Say which by its number.',
            default => $named->first(),
        };
    }

    private function missing(int $id, bool $deleted): string
    {
        if ($deleted) {
            return File::query()->whereKey($id)->exists()
                ? "File #{$id} isn't deleted."
                : "There is no deleted file #{$id}.";
        }

        return File::onlyTrashed()->whereKey($id)->exists()
            ? "File #{$id} was deleted. restore_file brings it back, if Josh asks."
            : "There is no file #{$id}.";
    }
}
