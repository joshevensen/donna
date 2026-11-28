<?php

use App\Enums\MessageAuthor;
use App\Files\FileWriter;
use App\Models\File;

beforeEach(function () {
    donnaExists();
    fakeEmbeddings();
});

it('undoes her last edit by writing the version before it as a new one', function () {
    $file = File::factory()->markdown("# Notes\n\nFibermade Co.\n")->create(['name' => 'notes.md']);

    [, $result] = donnaCalls([
        ['edit_file', ['file' => 'notes.md', 'operation' => 'replace', 'find' => 'Fibermade Co', 'replace' => 'Fibermade']],
        ['revert_file', ['file' => 'notes.md']],
    ], said: 'Undo that.');

    expect($result)->toBe('Reverted to version 1. notes.md is now at version 3.')
        ->and($file->refresh()->currentVersion)
        ->number->toBe(3)
        ->body->toBe("# Notes\n\nFibermade Co.\n")
        ->summary->toBe('Reverted to version 1')
        ->and($file->versions()->where('number', 2)->sole()->body)->toBe("# Notes\n\nFibermade.\n");
});

it('goes back to a version Josh names, and says so when there is no such version', function () {
    $file = File::factory()->markdown("one\n")->create(['name' => 'notes.md']);
    app(FileWriter::class)->write($file, "two\n", MessageAuthor::Josh, 'Edited by Josh');
    app(FileWriter::class)->write($file, "three\n", MessageAuthor::Josh, 'Edited by Josh');

    [$missing, $reverted] = donnaCalls([
        ['revert_file', ['file' => 'notes.md', 'version' => 9]],
        ['revert_file', ['file' => 'notes.md', 'version' => 2]],
    ]);

    expect($missing)->toBe('There is no version 9 of notes.md. It has versions 1 to 3. Nothing changed.')
        ->and($reverted)->toBe('Reverted to version 2. notes.md is now at version 4.')
        ->and($file->refresh()->body())->toBe("two\n");
});
