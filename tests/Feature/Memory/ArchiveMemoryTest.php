<?php

use App\Ai\Agents\Donna;
use App\Enums\ProjectStatus;
use App\Jobs\EmbedMessage;
use App\Jobs\GenerateReply;
use App\Jobs\TagTopics;
use App\Memory\Context\RelatedChunk;
use App\Memory\Context\ReplyContext;
use App\Models\Fact;
use App\Models\File;
use App\Models\FileChunk;
use App\Models\Message;
use App\Models\Project;
use Illuminate\Support\Facades\Exceptions;
use Illuminate\Support\Facades\Queue;
use Laravel\Ai\Embeddings;
use Laravel\Ai\Prompts\AgentPrompt;

/*
 | Archive Memory: kept files are found on their own. The same hybrid search
 | as messages and facts, over chunks, with the embedding Josh's message
 | already has.
 */

beforeEach(function () {
    Queue::fake([EmbedMessage::class, TagTopics::class]);
    donnaExists();
    config(['donna.context.recent_messages' => 0]);
});

/**
 * Have Donna answer, and return her reply and the context she was given.
 *
 * @return array{0: Message, 1: ReplyContext}
 */
function answerTo(string $body): array
{
    Donna::fake(['Okay.']);

    $reply = pendingReplyTo($body);

    GenerateReply::dispatchSync($reply);

    $context = null;

    Donna::assertNotPrompted(function (AgentPrompt $prompt) use (&$context): bool {
        $context = $prompt->agent->context;

        return false;
    });

    return [$reply->refresh(), $context];
}

/**
 * @return list<int>
 */
function chunkIds(ReplyContext $context): array
{
    return $context->memory->chunks->map(fn (RelatedChunk $related): int => $related->chunk->id)->all();
}

it('gives her the matching part of a kept file, with its file and heading, and lists it in what she looked at', function () {
    fakeEmbeddings(['returns' => 1]);
    $file = File::factory()->markdown()->kept()->create(['name' => 'handbook.md', 'created_at' => '2026-11-24 18:00:00']);
    $chunk = FileChunk::factory()->for($file)->create(['heading_path' => 'Handbook › Returns', 'body' => "Thirty days, unworn.\nStore credit after that.", 'embedding' => embedding(1)]);
    FileChunk::factory()->for($file)->create(['heading_path' => 'Handbook › Shipping', 'body' => 'Tuesdays and Fridays.', 'embedding' => embedding(2)]);

    [$reply, $context] = answerTo('What is our returns window?');

    expect(chunkIds($context))->toBe([$chunk->id])
        ->and($context->prompt)->toContain(
            "## From kept files (the parts that match, not whole files)\n"
            ."- [file #{$file->id} \"handbook.md\" › Handbook › Returns | arrived 2026-11-24]\n"
            ."    Thirty days, unworn.\n"
            .'    Store credit after that.'
        )
        ->and($reply->meta['sources'])->toBe([['type' => 'file', 'label' => 'handbook.md › Handbook › Returns', 'file_id' => $file->id]]);
});

it('finds a kept file by its words alone when the message has no embedding', function () {
    fakeEmbeddings();
    Embeddings::fake(fn () => throw new RuntimeException('Voyage is down.'));
    Exceptions::fake();
    $chunk = FileChunk::factory()->create(['body' => 'Load-in at the east dock is Thursday at seven.']);

    [, $context] = answerTo('When is load-in at the dock?');

    expect(chunkIds($context))->toBe([$chunk->id]);
});

it('draws nothing from a file that isn\'t kept, or is deleted', function (Closure $file) {
    fakeEmbeddings(['returns' => 1]);
    FileChunk::factory()->for($file())->create(['body' => 'Returns: thirty days.', 'embedding' => embedding(1)]);

    [, $context] = answerTo('What is our returns window?');

    expect($context->memory->chunks)->toBeEmpty();
})->with([
    'a working file' => [fn () => File::factory()->markdown()->create()],
    'a deleted kept file' => [fn () => tap(File::factory()->markdown()->kept()->create())->delete()],
]);

it('ranks the part of a file linked to a related project above an otherwise equal one', function () {
    fakeEmbeddings(['pricing' => 1]);
    $project = Project::factory()->create(['name' => 'Fibermade', 'status' => ProjectStatus::Active]);
    $other = FileChunk::factory()->create(['body' => 'Pricing is a flat monthly plan.', 'embedding' => embedding(1)]);
    $linked = FileChunk::factory()->create(['body' => 'Pricing is a flat monthly plan.', 'embedding' => embedding(1)]);
    $linked->file->projects()->attach($project);

    [, $context] = answerTo('Remind me of the Fibermade pricing.');

    expect(chunkIds($context))->toBe([$linked->id, $other->id]);
});

it('leaves out the parts of a file already in play: she has that file to hand', function () {
    fakeEmbeddings(['returns' => 1]);
    $inPlay = File::factory()->markdown()->kept()->on(Message::factory()->create())->create();
    FileChunk::factory()->for($inPlay)->create(['body' => 'Returns: thirty days.', 'embedding' => embedding(1)]);
    $elsewhere = FileChunk::factory()->create(['body' => 'Returns: store credit only.', 'embedding' => embedding(1)]);

    [, $context] = answerTo('What is our returns window?');

    expect(chunkIds($context))->toBe([$elsewhere->id]);
});

it('makes file parts compete with facts for room: the weakest match of either kind is dropped first', function () {
    fakeEmbeddings(['returns' => 1]);
    $strong = FileChunk::factory()->create(['body' => 'Returns window: thirty days.', 'embedding' => embedding(1)]);
    FileChunk::factory()->create(['body' => str_repeat('Unrelated filler about returns policy wording. ', 30), 'embedding' => embedding(2)]);
    $fact = Fact::factory()->create(['statement' => 'Josh extended the returns window to thirty days.', 'embedding' => embedding(1)]);
    config(['donna.context.memory_token_budget' => 110]);

    [, $context] = answerTo('What is our returns window?');

    expect(chunkIds($context))->toBe([$strong->id])
        ->and($context->memory->facts->map(fn ($related): int => $related->fact->id)->all())->toBe([$fact->id]);
});
