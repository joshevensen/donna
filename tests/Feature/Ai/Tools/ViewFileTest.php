<?php

use App\Ai\Agents\Donna;
use App\Enums\CostPurpose;
use App\Jobs\EmbedMessage;
use App\Jobs\GenerateReply;
use App\Jobs\TagTopics;
use App\Models\Cost;
use App\Models\File;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Queue;
use Laravel\Ai\Files\Base64Document;
use Laravel\Ai\Files\Base64Image;
use Laravel\Ai\Prompts\AgentPrompt;
use Laravel\Ai\Responses\Data\ToolCall;

/*
 | The SDK can't return an image from a tool, so `view_file` only notes the
 | request. The reply job then makes one more call with the file attached,
 | and that call's answer is the reply.
 */

beforeEach(function () {
    Queue::fake([EmbedMessage::class, TagTopics::class]);
    donnaExists();
    fakeEmbeddings();
});

/**
 * Every prompt Donna was sent while writing the reply, in order.
 *
 * @return Collection<int, AgentPrompt>
 */
function promptsSent(): Collection
{
    $prompts = collect();

    // "Not prompted with" visits every prompt, where "prompted with" stops at the first match.
    Donna::assertNotPrompted(function (AgentPrompt $prompt) use ($prompts): bool {
        $prompts->push($prompt);

        return false;
    });

    return $prompts;
}

it('shows her the image in a second call whose answer is the reply, without redoing what the first call did', function () {
    $photo = File::factory()->image()->create(['name' => 'booth.png']);
    $notes = File::factory()->markdown("# Notes\n")->create(['name' => 'notes.md']);
    Donna::fake([
        new ToolCall('call_1', 'edit_file', ['file' => 'notes.md', 'operation' => 'append', 'text' => '- Check the booth photo']),
        new ToolCall('call_2', 'view_file', ['file' => 'booth.png']),
        'Let me take a look.',
        'It\'s the east dock booth: blue yarn on the left wall.',
    ]);
    $reply = pendingReplyTo('Look at that booth photo again and note it.');

    GenerateReply::dispatchSync($reply);

    [$first, $second] = promptsSent();

    expect($reply->refresh()->body)->toBe('It\'s the east dock booth: blue yarn on the left wall.')
        ->and($first->attachments)->toHaveCount(0)
        ->and($second->attachments)->toHaveCount(1)
        ->and($second->attachments->first())->toBeInstanceOf(Base64Image::class)
        ->and($second->attachments->first()->base64)->toBe(base64_encode('png bytes'))
        ->and($second->attachments->first()->mime)->toBe('image/png')
        // She is told what the first call already did, and it happened once.
        ->and($second->prompt)->toContain("booth.png (file #{$photo->id})")->toContain('- edit_file {"file":"notes.md","operation":"append"')
        ->and($notes->versions()->count())->toBe(2)
        ->and($notes->refresh()->body())->toBe("# Notes\n- Check the booth photo\n")
        // Three steps in the first call, one in the second: each its own cost.
        ->and(Cost::where('purpose', CostPurpose::Reply)->count())->toBe(4);
});

it('shows her a scanned PDF as the document itself, and names the page she asked for', function () {
    $scan = File::factory()->pdf([], scannedPages: 3)->create(['name' => 'receipt-scan.pdf']);
    Donna::fake([new ToolCall('call_1', 'view_file', ['file' => 'receipt-scan.pdf', 'page' => 2]), 'One moment.', 'Page 2 is the deposit receipt: $450.']);

    GenerateReply::dispatchSync(pendingReplyTo('What does page 2 of the scan say?'));

    [, $second] = promptsSent();

    expect($second->attachments->sole())->toBeInstanceOf(Base64Document::class)
        ->and($second->attachments->sole()->mime)->toBe('application/pdf')
        ->and($second->prompt)->toContain("receipt-scan.pdf (file #{$scan->id}), page 2 in particular");
});

it('refuses a file with text, or one that isn\'t there, and makes no second call', function () {
    File::factory()->markdown()->create(['name' => 'plan.md']);

    [$text, $missing] = donnaCalls([
        ['view_file', ['file' => 'plan.md']],
        ['view_file', ['file' => 'nothing.png']],
    ]);

    expect($text)->toBe('plan.md is a markdown file: it has text, not pictures. Use read_file.')
        ->and($missing)->toBe('There is no file called "nothing.png". Use list_files to see what there is.');

    Donna::assertPromptedTimes(1);
});

it('lets her look at two files in a reply and no more', function () {
    File::factory()->image()->count(3)->sequence(['name' => 'one.png'], ['name' => 'two.png'], ['name' => 'three.png'])->create();
    Donna::fake([
        new ToolCall('call_1', 'view_file', ['file' => 'one.png']),
        'Looking.',
        new ToolCall('call_2', 'view_file', ['file' => 'two.png']),
        new ToolCall('call_3', 'view_file', ['file' => 'three.png']),
        'Looking again.',
        'The first two are the booth; I\'ll look at the third next.',
    ]);
    $reply = pendingReplyTo('Compare the three photos.');

    GenerateReply::dispatchSync($reply);

    $prompts = promptsSent();

    expect($prompts)->toHaveCount(3)
        ->and($prompts->map(fn (AgentPrompt $prompt): int => $prompt->attachments->count())->all())->toBe([0, 1, 1])
        ->and($reply->refresh()->body)->toBe('The first two are the booth; I\'ll look at the third next.');
});
