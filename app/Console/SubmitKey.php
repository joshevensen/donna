<?php

namespace App\Console;

use Laravel\Prompts\TextareaPrompt;

/**
 * A function key that sends the message in `donna:chat`, as an alternative
 * to Ctrl+D. Enter adds a line in the multi-line prompt, and Ctrl+D is a
 * two-handed chord on a phone's on-screen keyboard; Blink Shell has F keys
 * in its Smart Keys bar.
 */
final class SubmitKey
{
    /**
     * What a terminal sends for each key. F1 to F4 have two forms in the
     * wild; F5 and up have one.
     *
     * @var array<string, list<string>>
     */
    private const array SEQUENCES = [
        'f1' => ["\eOP", "\e[11~"],
        'f2' => ["\eOQ", "\e[12~"],
        'f3' => ["\eOR", "\e[13~"],
        'f4' => ["\eOS", "\e[14~"],
        'f5' => ["\e[15~"],
        'f6' => ["\e[17~"],
        'f7' => ["\e[18~"],
        'f8' => ["\e[19~"],
        'f9' => ["\e[20~"],
        'f10' => ["\e[21~"],
        'f11' => ["\e[23~"],
        'f12' => ["\e[24~"],
    ];

    private ?string $key;

    /**
     * @param  string|null  $name  "f1" to "f12". Anything else leaves Ctrl+D as the only way to send.
     */
    public function __construct(?string $name)
    {
        $name = $name === null ? null : mb_strtolower(trim($name));

        $this->key = isset(self::SEQUENCES[$name]) ? $name : null;
    }

    public static function configured(): self
    {
        return new self(config('donna.client.submit_key'));
    }

    /**
     * The reminder shown under the input.
     */
    public function hint(): string
    {
        return 'Send with Ctrl+D'.($this->key === null ? '' : ' or '.mb_strtoupper($this->key));
    }

    /**
     * Make the key send the prompt, exactly as Ctrl+D does.
     */
    public function attachTo(TextareaPrompt $prompt): void
    {
        if ($this->key === null) {
            return;
        }

        $sequences = self::SEQUENCES[$this->key];

        $prompt->on('key', function (string $key) use ($prompt, $sequences): void {
            if (in_array($key, $sequences, true)) {
                // submit() is protected; Ctrl+D calls it from inside the prompt, so this does too.
                (fn () => $this->submit())->call($prompt);
            }
        });
    }
}
