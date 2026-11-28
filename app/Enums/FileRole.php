<?php

namespace App\Enums;

/**
 * What a message had to do with a file: Josh attached it, or Donna created,
 * edited or opened it while writing that reply.
 */
enum FileRole: string
{
    case Attached = 'attached';
    case Created = 'created';
    case Edited = 'edited';
    case Opened = 'opened';

    /**
     * When one message touched a file in more than one way, the card shows
     * the one that says the most.
     */
    public function weight(): int
    {
        return match ($this) {
            self::Attached => 4,
            self::Created => 3,
            self::Edited => 2,
            self::Opened => 1,
        };
    }
}
