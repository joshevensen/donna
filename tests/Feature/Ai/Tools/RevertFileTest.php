<?php

use App\Ai\Agents\Donna;
use App\Enums\MessageAuthor;
use App\Files\FileWriter;
use App\Jobs\EmbedMessage;
use App\Jobs\GenerateReply;
use App\Jobs\TagTopics;
use App\Models\File;
use Illuminate\Support\Facades\Queue;
use Laravel\Ai\Responses\Data\ToolCall;

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

it('undoes everything she changed in her last reply, not just the last of several edits', function () {
    $file = File::factory()->markdown("one\ntwo\nthree\n")->create(['name' => 'notes.md']);

    donnaCalls([
        ['edit_file', ['file' => 'notes.md', 'operation' => 'delete_lines', 'from_line' => 1]],
        ['edit_file', ['file' => 'notes.md', 'operation' => 'append', 'text' => 'four']],
    ], said: 'Drop the first line and add four.');

    [$result] = donnaCalls([['revert_file', ['file' => 'notes.md']]], said: 'Undo that.');

    expect($result)->toBe('Reverted to version 1. notes.md is now at version 4.')
        ->and($file->refresh()->body())->toBe("one\ntwo\nthree\n");
});

// Once the undo has happened, "her last edit" is a different edit. The retry must not undo the undo.
it('does not undo the undo when the reply it was made in runs again', function () {
    Queue::fake([EmbedMessage::class, TagTopics::class]);
    $file = File::factory()->markdown("# Notes\n")->create(['name' => 'notes.md']);
    donnaCalls([['edit_file', ['file' => 'notes.md', 'operation' => 'append', 'text' => 'A line Josh did not want.']]]);
    $reply = pendingReplyTo('Undo that.');
    $undo = new ToolCall('call_1', 'revert_file', ['file' => 'notes.md']);

    Donna::fake([$undo, '']);
    expect(fn () => app()->call([new GenerateReply($reply), 'handle']))->toThrow(RuntimeException::class);

    Donna::fake([$undo, 'Undone.']);
    GenerateReply::dispatchSync($reply->refresh());

    expect($file->refresh()->body())->toBe("# Notes\n")
        ->and($file->versions()->count())->toBe(3);
});
