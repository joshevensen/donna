<?php

namespace App\Files;

use App\Models\File;

/**
 * The images and scans Donna asked to look at while writing one reply. A
 * tool can only answer in text, so `view_file` notes the request here and
 * the reply job shows her the file in one more call.
 */
class Looks
{
    /**
     * @var list<array{file: File, page: int|null}>
     */
    private array $waiting = [];

    private int $taken = 0;

    /**
     * Ask to be shown a file. False when this reply has had its looks.
     */
    public function ask(File $file, ?int $page = null): bool
    {
        if ($this->taken + count($this->waiting) >= config('donna.files.looks_per_reply')) {
            return false;
        }

        $this->waiting[] = ['file' => $file, 'page' => $page];

        return true;
    }

    public function areWaiting(): bool
    {
        return $this->waiting !== [];
    }

    /**
     * Hand over what was asked for, to be shown now.
     *
     * @return list<array{file: File, page: int|null}>
     */
    public function take(): array
    {
        $waiting = $this->waiting;

        $this->taken += count($waiting);
        $this->waiting = [];

        return $waiting;
    }
}
