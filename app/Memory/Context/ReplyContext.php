<?php

namespace App\Memory\Context;

use Laravel\Ai\Files\File as Attachment;
use Laravel\Ai\Messages\Message as Turn;

/**
 * The full context for one reply, in the order the model reads it. The two
 * system parts are stable between messages so the provider can cache them.
 */
final readonly class ReplyContext
{
    /**
     * @param  string  $character  Donna's active profile and her operating instructions.
     * @param  string  $profile  Josh's summary and his tracked projects.
     * @param  list<Turn>  $turns  The recent conversation, oldest first.
     * @param  string  $prompt  The memory block followed by Josh's message.
     * @param  list<array{type: string, label: string, file_id?: int}>  $sources  The top of the memory block, named for "What I looked at".
     * @param  list<Attachment>  $attachments  Images and scans Josh attached, shown to the model itself for this turn only.
     */
    public function __construct(
        public string $character,
        public string $profile,
        public array $turns,
        public string $prompt,
        public Memory $memory,
        public array $sources = [],
        public array $attachments = [],
    ) {}
}
