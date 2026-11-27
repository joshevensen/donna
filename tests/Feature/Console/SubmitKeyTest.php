<?php

use App\Console\SubmitKey;
use Laravel\Prompts\Prompt;
use Laravel\Prompts\TextareaPrompt;

/**
 * Type the given keys into the same prompt `donna:chat` uses and return what it sends.
 *
 * @param  list<string>  $keys
 */
function typeIntoPrompt(SubmitKey $submitKey, array $keys): string
{
    Prompt::fake($keys);

    $prompt = new TextareaPrompt(label: 'You', required: true);
    $submitKey->attachTo($prompt);

    return $prompt->prompt();
}

afterEach(fn () => Prompt::flushState());

// Sending a message from a phone: Ctrl+D is a two-handed chord on Blink's on-screen keyboard.
it('sends the message when the configured function key is pressed, as Ctrl+D does', function (string $name, string $sequence) {
    expect(typeIntoPrompt(new SubmitKey($name), ['h', 'i', $sequence]))->toBe('hi');
})->with([
    'F5, which terminals send one way' => ['f5', "\e[15~"],
    'F5 configured in capitals' => ['F5', "\e[15~"],
    'F1 as xterm sends it' => ['f1', "\eOP"],
    'F1 as the VT220 family sends it' => ['f1', "\e[11~"],
]);

it('ignores other function keys rather than sending early or typing them into the message', function () {
    expect(typeIntoPrompt(new SubmitKey('f5'), ['h', "\e[17~", 'i', "\x04"]))->toBe('hi');
});

it('leaves Ctrl+D as the only way to send when no key is configured', function (?string $name) {
    expect(typeIntoPrompt(new SubmitKey($name), ['h', "\e[15~", 'i', "\x04"]))->toBe('hi');
})->with([
    'blank' => [''],
    'unset' => [null],
    'not a function key' => ['banana'],
]);

it('still adds a line on Enter and keeps typing', function () {
    expect(typeIntoPrompt(new SubmitKey('f5'), ['a', "\n", 'b', "\e[15~"]))->toBe("a\nb");
});
