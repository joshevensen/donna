<?php

use App\Enums\CostPurpose;
use App\Enums\MessageAuthor;
use App\Files\FileLibrary;
use App\Files\FileWriter;
use App\Jobs\EmbedMissing;
use App\Jobs\IndexFile;
use App\Models\Cost;
use App\Models\File;
use App\Models\FileChunk;
use Illuminate\Support\Facades\Exceptions;
use Laravel\Ai\Embeddings;
use Laravel\Ai\Prompts\EmbeddingsPrompt;

const HANDBOOK = "# Handbook\n\n## Returns\n\nThirty days, unworn.\n\n## Shipping\n\nTuesdays and Fridays.\n";

it('chunks and embeds a kept file, charging the embedding to the file', function () {
    fakeEmbeddings(['returns' => 1, 'shipping' => 2]);
    $file = File::factory()->markdown(HANDBOOK)->kept()->create(['name' => 'handbook.md']);

    IndexFile::dispatchSync($file->id);

    expect($file->chunks()->orderBy('position')->get()->map->only(['position', 'heading_path', 'body', 'embedding'])->all())->toBe([
        ['position' => 0, 'heading_path' => 'Handbook › Returns', 'body' => 'Thirty days, unworn.', 'embedding' => embedding(1)],
        ['position' => 1, 'heading_path' => 'Handbook › Shipping', 'body' => 'Tuesdays and Fridays.', 'embedding' => embedding(2)],
    ])
        ->and(Cost::where('purpose', CostPurpose::Embedding)->sole())
        ->trigger_type->toBe('file')
        ->trigger_id->toBe($file->id);
});

it('never chunks or embeds a working file', function () {
    Embeddings::fake()->preventStrayEmbeddings();
    $file = File::factory()->markdown(HANDBOOK)->create();

    IndexFile::dispatchSync($file->id);
    app(FileWriter::class)->write($file, HANDBOOK."\nMore.\n", MessageAuthor::Josh, 'Edited by Josh');

    expect(FileChunk::count())->toBe(0)
        ->and(Cost::count())->toBe(0);
});

it('re-embeds only the part of a kept file that an edit changed', function () {
    $embedded = collect();
    Embeddings::fake(function (EmbeddingsPrompt $prompt) use ($embedded): array {
        $embedded->push($prompt->inputs);

        return array_map(fn (): array => embedding(3), $prompt->inputs);
    });
    $file = File::factory()->markdown(HANDBOOK)->kept()->create(['name' => 'handbook.md']);
    IndexFile::dispatchSync($file->id);
    $returns = $file->chunks()->where('heading_path', 'Handbook › Returns')->sole();

    app(FileWriter::class)->write($file, str_replace('Tuesdays and Fridays.', 'Every weekday.', HANDBOOK), MessageAuthor::Donna, 'Replaced 1 match');

    expect($embedded->all())->toBe([
        ["handbook.md › Handbook › Returns\nThirty days, unworn.", "handbook.md › Handbook › Shipping\nTuesdays and Fridays."],
        ["handbook.md › Handbook › Shipping\nEvery weekday."],
    ])
        ->and($file->chunks()->orderBy('position')->pluck('body')->all())->toBe(['Thirty days, unworn.', 'Every weekday.'])
        ->and($returns->fresh())->not->toBeNull()
        ->and($returns->fresh()->embedding)->toBe(embedding(3));
});

it('saves the chunks without vectors when embeddings are down, and EmbedMissing fills them in later', function () {
    Exceptions::fake();
    Embeddings::fake(fn () => throw new RuntimeException('Voyage is down.'));
    $file = File::factory()->markdown(HANDBOOK)->create(['name' => 'handbook.md']);

    app(FileLibrary::class)->keep($file, true);

    expect($file->chunks()->count())->toBe(2)
        ->and($file->chunks()->whereNotNull('embedding')->count())->toBe(0);

    fakeEmbeddings(['returns' => 1, 'shipping' => 2]);
    EmbedMissing::dispatchSync();

    expect($file->chunks()->orderBy('position')->get()->map->embedding->all())->toBe([embedding(1), embedding(2)]);
});

it('embeds a renamed file again, since each chunk is embedded with its file\'s name', function () {
    fakeEmbeddings(['policies' => 5]);
    $file = File::factory()->markdown(HANDBOOK)->kept()->create(['name' => 'handbook.md']);
    IndexFile::dispatchSync($file->id);

    app(FileLibrary::class)->rename($file, 'policies.md');

    expect($file->chunks()->get()->map->embedding->unique()->all())->toBe([embedding(5)]);
});
