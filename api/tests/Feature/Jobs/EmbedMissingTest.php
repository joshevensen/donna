<?php

use App\Enums\CostPurpose;
use App\Jobs\EmbedMissing;
use App\Models\Cost;
use App\Models\Fact;
use App\Models\Message;
use App\Models\Topic;
use Laravel\Ai\Embeddings;

it('embeds whatever was saved without a vector, one call per kind', function () {
    fakeEmbeddings(['gate' => 1, 'supplier' => 2, 'pricing' => 3]);
    $message = Message::factory()->create(['body' => 'The supplier called.']);
    $fact = Fact::factory()->create(['statement' => 'The gate code is 4471.']);
    $topic = Topic::factory()->create(['name' => 'Fibermade', 'summary' => 'Pricing settled at $29.']);
    $already = Fact::factory()->create(['statement' => 'About the gate.', 'embedding' => embedding(9)]);
    Message::factory()->pending()->create();

    EmbedMissing::dispatchSync();

    expect($message->refresh()->embedding)->toBe(embedding(2))
        ->and($fact->refresh()->embedding)->toBe(embedding(1))
        ->and($topic->refresh()->embedding)->toBe(embedding(3))
        ->and($already->refresh()->embedding)->toBe(embedding(9))
        ->and(Cost::where('purpose', CostPurpose::Embedding)->count())->toBe(3);
});

it('makes no calls when nothing is missing', function () {
    Embeddings::fake()->preventStrayEmbeddings();
    Fact::factory()->create(['embedding' => embedding(1)]);

    EmbedMissing::dispatchSync();

    expect(Cost::count())->toBe(0);
});
