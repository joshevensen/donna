<?php

namespace App\Memory\Context;

use App\Models\File;
use Laravel\Ai\Files\File as Attachment;

/**
 * A file Josh attached to the message being answered, as Donna gets it.
 */
final readonly class AttachedFile
{
    /**
     * @param  string  $given  How much of it she was given, stated by code: "read in full", "outline only: 12 of 3,200 lines", and so on.
     * @param  string|null  $text  The text that goes into the prompt: the whole file, or its outline.
     * @param  Attachment|null  $attachment  An image or a scan, shown to the model itself for this turn only.
     * @param  bool  $whole  All of its text is in the prompt, so retrieval has nothing to add from it.
     */
    public function __construct(
        public File $file,
        public string $given,
        public ?string $text = null,
        public ?Attachment $attachment = null,
        public bool $whole = false,
    ) {}
}
