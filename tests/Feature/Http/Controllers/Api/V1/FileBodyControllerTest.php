<?php

use App\Enums\MessageAuthor;
use App\Files\FileWriter;
use App\Models\File;
use App\Models\User;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\putJson;

beforeEach(fn () => actingAs(User::factory()->create()));

it('saves Josh\'s edit as a new version, exactly as typed', function () {
    $file = File::factory()->markdown("# Plan\n")->create();

    putJson("/api/v1/files/{$file->id}", ['body' => "\n# Plan\n\nShip it.  \n\n", 'base_version' => 1])
        ->assertOk()
        ->assertJsonPath('data.version', 2)
        ->assertJsonPath('data.last_author', 'josh')
        ->assertJsonPath('data.body', "\n# Plan\n\nShip it.  \n\n");

    expect($file->refresh()->currentVersion)
        ->number->toBe(2)
        ->author->toBe(MessageAuthor::Josh)
        ->body->toBe("\n# Plan\n\nShip it.  \n\n")
        ->message_id->toBeNull();
});

it('lets a file be emptied', function () {
    $file = File::factory()->markdown("# Plan\n")->create();

    putJson("/api/v1/files/{$file->id}", ['body' => '', 'base_version' => 1])->assertOk()->assertJsonPath('data.body', '');
});

it('refuses a save made on top of a version that is no longer current, and hands back the current file', function () {
    $file = File::factory()->markdown("# Plan\n")->create();
    app(FileWriter::class)->write($file, "# Plan\n\nDonna's line.\n", MessageAuthor::Donna, 'Appended 2 lines');

    putJson("/api/v1/files/{$file->id}", ['body' => "# Plan\n\nJosh's line.\n", 'base_version' => 1])
        ->assertConflict()
        ->assertJsonPath('data.version', 2)
        ->assertJsonPath('data.last_author', 'donna')
        ->assertJsonPath('data.body', "# Plan\n\nDonna's line.\n");

    expect($file->versions()->count())->toBe(2)
        ->and($file->refresh()->body())->toBe("# Plan\n\nDonna's line.\n");
});

it('refuses to edit anything but markdown', function (Closure $file) {
    $file = $file();

    putJson("/api/v1/files/{$file->id}", ['body' => 'changed', 'base_version' => 1])->assertUnprocessable();

    expect($file->versions()->count())->toBe(1);
})->with([
    'a CSV' => [fn () => File::factory()->csv()->create()],
    'a PDF' => [fn () => File::factory()->pdf()->create()],
]);

it('refuses a save that could not be stored or would cut the file short', function (Closure $file, string $body) {
    $file = $file();

    putJson("/api/v1/files/{$file->id}", ['body' => $body, 'base_version' => 1])->assertUnprocessable();

    expect($file->versions()->count())->toBe(1);
})->with([
    'text with a NUL byte in it' => [fn () => File::factory()->markdown("# Plan\n")->create(), "# Plan\n\0"],
    'a file longer than the viewer is ever sent' => [fn () => tap(File::factory()->markdown("# Plan\n")->create())->update(['size_bytes' => 600_000]), '# The first part only'],
]);

it('keeps Josh\'s change when Donna edits the file after he saved', function () {
    donnaExists();
    fakeEmbeddings();
    $file = File::factory()->markdown("# Plan\n\nFibermade Co launches Jan 25.\n")->create(['name' => 'plan.md']);

    putJson("/api/v1/files/{$file->id}", ['body' => "# Plan\n\nFibermade Co launches Feb 1.\n", 'base_version' => 1])->assertOk();

    donnaCalls([['edit_file', ['file' => 'plan.md', 'operation' => 'replace', 'find' => 'Fibermade Co', 'replace' => 'Fibermade']]]);

    expect($file->refresh()->body())->toBe("# Plan\n\nFibermade launches Feb 1.\n")
        ->and($file->currentVersion->number)->toBe(3);
});
