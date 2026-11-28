<?php

namespace App\Files;

use App\Enums\FileKind;

/**
 * What an upload turned out to be.
 */
final readonly class Sniffed
{
    /**
     * @param  string  $mime  The type the content was found to be, which is the only one it is ever served as.
     * @param  string|null  $text  The content as UTF-8, for the kinds that are text.
     */
    public function __construct(
        public FileKind $kind,
        public string $mime,
        public ?string $text = null,
    ) {}
}
