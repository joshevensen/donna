<?php

use App\Conversation\Bubbles;

// A reply reads as several texts only where Donna put a `---` line of her own.
it('splits a reply into bubbles on lines that are only ---', function (string $body, array $bubbles) {
    expect(Bubbles::split($body))->toBe($bubbles);
})->with([
    'no separator is one bubble' => ["You kept guest checkout.\n\nTwo reasons.", ["You kept guest checkout.\n\nTwo reasons."]],
    'a separator between texts' => ["First.\n---\nSecond.\n\n---\n\nThird.", ['First.', 'Second.', 'Third.']],
    'a separator with trailing spaces or Windows line endings' => ["First.\r\n---  \r\nSecond.", ['First.', 'Second.']],
    'dashes that are part of a line are text' => ["A --- B\n----\n- --- -", ["A --- B\n----\n- --- -"]],
    'a separator inside a fenced code block is code' => [
        "Front matter:\n```yaml\n---\ntitle: Donna\n---\n```\n---\nThat's it.",
        ["Front matter:\n```yaml\n---\ntitle: Donna\n---\n```", "That's it."],
    ],
    'a tilde fence, and a shorter fence inside a longer one' => [
        "~~~\n---\n~~~\n---\n````\n```\n---\n```\n````",
        ["~~~\n---\n~~~", "````\n```\n---\n```\n````"],
    ],
    'a code block she never closed runs to the end' => ["```\n---\nstill code", ["```\n---\nstill code"]],
    'empty bubbles are dropped' => ["---\n\n---\nOnly this.\n---\n   \n---", ['Only this.']],
    'an empty body has no bubbles' => ['', []],
]);
