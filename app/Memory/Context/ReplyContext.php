<?php

namespace App\Memory\Context;

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
     * @param  list<array{type: string, label: string}>  $sources  The top of the memory block, named for "What I looked at".
     */
    public function __construct(
        public string $character,
        public string $profile,
        public array $turns,
        public string $prompt,
        public Memory $memory,
        public array $sources = [],
    ) {}
}
