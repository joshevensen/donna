<?php

namespace App\Memory\Context;

use App\Models\Topic;

/**
 * A topic the message is about, with how often Josh has come back to it.
 */
final readonly class RelatedTopic
{
    /**
     * @param  int|null  $daysIdle  Whole days since it was last discussed.
     * @param  bool  $stale  Idle long enough that Donna should open with a recap.
     * @param  array<int, array{messages: int, days: int}>  $mentions  Josh's messages and distinct days, keyed by window length in days.
     */
    public function __construct(
        public Topic $topic,
        public ?int $daysIdle,
        public bool $stale,
        public array $mentions,
    ) {}
}
