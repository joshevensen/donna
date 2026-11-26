<?php

namespace App\Memory\Context;

use App\Models\Message;

/**
 * A past message of Josh's that retrieval surfaced, with the reply it got.
 */
final readonly class RelatedExchange
{
    public function __construct(
        public Message $message,
        public ?Message $reply,
        public float $score,
    ) {}
}
