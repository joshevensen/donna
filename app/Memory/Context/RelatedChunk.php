<?php

namespace App\Memory\Context;

use App\Models\FileChunk;

/**
 * A piece of a kept file that retrieval surfaced, with its file loaded.
 */
final readonly class RelatedChunk
{
    public function __construct(
        public FileChunk $chunk,
        public float $score,
    ) {}
}
