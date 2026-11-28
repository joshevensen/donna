<?php

namespace App\Memory\Context;

use App\Enums\FileRole;
use App\Models\File;
use App\Models\Message;

/**
 * A file attached or touched in the last stretch of conversation: what
 * "that file" and "the second one" refer to.
 */
final readonly class FileInPlay
{
    /**
     * @param  Message|null  $arrivedWith  The message of Josh's it came with, or that Donna was answering when she wrote it.
     * @param  FileRole|null  $arrivedAs  Attached by Josh, or created by Donna.
     * @param  string|null  $joshChanges  What Josh changed by hand since Donna last saw the file, as a diff or the sections that changed.
     */
    public function __construct(
        public File $file,
        public ?Message $arrivedWith,
        public ?FileRole $arrivedAs,
        public ?string $joshChanges = null,
    ) {}
}
