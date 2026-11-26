<?php

use App\Ai\Agents\Donna;
use App\Enums\CostPurpose;
use App\Enums\MessageStatus;
use App\Jobs\EmbedMessage;
use App\Jobs\GenerateReply;
use App\Jobs\TagTopics;
use App\Models\Cost;
use App\Models\Fact;
use App\Models\Message;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Exceptions;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Laravel\Ai\Embeddings;
use Laravel\Ai\Prompts\AgentPrompt;
use Laravel\Ai\Responses\Data\Meta;
use Laravel\Ai\Responses\Data\TextUsage;
use Laravel\Ai\Responses\Data\ToolCall;
use Laravel\Ai\Responses\Data\UrlCitation;
use Laravel\Ai\Responses\TextResponse;

it('writes Donna\'s reply onto the pending message and queues embedding and tagging', function () {
    Queue::fake([EmbedMessage::class, TagTopics::class]);
    donnaExists();
    fakeEmbeddings();
    Donna::fake(['Parked. I\'ll bring it back when you have room.']);
    $reply = pendingReplyTo('Park Storyrift for now.');

    GenerateReply::dispatchSync($reply);

    expect($reply->refresh())
        ->status->toBe(MessageStatus::Complete)
        ->body->toBe('Parked. I\'ll bring it back when you have room.')
        ->error->toBeNull()
        ->and($reply->inReplyTo->embedding)->toBe(embedding(0));

    Donna::assertPrompted(fn ($prompt): bool => $prompt->contains('Park Storyrift for now.'));
    Queue::assertPushed(EmbedMessage::class, fn (EmbedMessage $job): bool => $job->message->is($reply));
    Queue::assertPushed(TagTopics::class, fn (TagTopics $job): bool => $job->message->is($reply->inReplyTo) && $job->reply->is($reply));
});

it('records exactly one cost per model call, charged to the message that caused it', function () {
    Queue::fake([EmbedMessage::class, TagTopics::class]);
    donnaExists();
    fakeEmbeddings();
    Donna::fake([
        new ToolCall('call_1', 'remember', ['statement' => 'Josh\'s anniversary is June 3.']),
        new TextResponse('Got it.', new TextUsage(10_000, 2_000, 6_000, 1_000), new Meta('anthropic', 'claude-sonnet-5-5')),
    ]);
    $reply = pendingReplyTo('Remember this: our anniversary is June 3.');

    GenerateReply::dispatchSync($reply);

    // Embedding Josh's message, the tool-call step, embedding the new fact, the answering step.
    expect(Cost::where('purpose', CostPurpose::Embedding)->count())->toBe(2)
        ->and(Cost::where('purpose', CostPurpose::Reply)->count())->toBe(2)
        ->and(Cost::where('trigger_type', 'message')->where('trigger_id', $reply->in_reply_to_id)->count())->toBe(4)
        ->and(Cost::where('purpose', CostPurpose::Reply)->latest('id')->first())
        ->model->toBe('claude-sonnet-5-5')
        ->input_tokens->toBe(10_000)
        ->cache_read_tokens->toBe(6_000)
        ->cache_write_tokens->toBe(1_000)
        ->output_tokens->toBe(2_000)
        ->usd->toBe('0.029100')
        ->and(Fact::sole()->statement)->toBe('Josh\'s anniversary is June 3.');
});

it('keeps the web sources Donna cited with her reply', function () {
    Queue::fake([EmbedMessage::class, TagTopics::class]);
    donnaExists();
    fakeEmbeddings();
    Donna::fake([
        new TextResponse('It ships Tuesday.', new TextUsage, new Meta('anthropic', 'claude-sonnet-5-5', collect([
            new UrlCitation('https://example.com/ship', 'Shipping'),
        ]))),
    ]);
    $reply = pendingReplyTo('When does the new keyboard ship?');

    GenerateReply::dispatchSync($reply);

    expect($reply->refresh()->meta['citations'])->toBe([['url' => 'https://example.com/ship', 'title' => 'Shipping']]);
});

it('sends Anthropic the cached system blocks, the recent turns, and its tools, and bills web searches', function () {
    Queue::fake([EmbedMessage::class, TagTopics::class]);
    donnaExists();
    Embeddings::fake();
    $earlier = Message::factory()->create(['body' => 'Morning.']);
    Message::factory()->replyTo($earlier)->create(['body' => 'Morning, Josh.']);
    $reply = pendingReplyTo('What did the Fed do today?');

    Http::fake(['api.anthropic.com/v1/messages' => Http::response([
        'id' => 'msg_1',
        'type' => 'message',
        'role' => 'assistant',
        'model' => 'claude-sonnet-5-5',
        'content' => [['type' => 'text', 'text' => 'Held rates.']],
        'stop_reason' => 'end_turn',
        'usage' => ['input_tokens' => 1_000, 'output_tokens' => 100, 'server_tool_use' => ['web_search_requests' => 2]],
    ])]);

    GenerateReply::dispatchSync($reply);

    Http::assertSent(function (Request $request): bool {
        $tools = collect($request['tools']);

        return $request->hasHeader('anthropic-beta', 'web-fetch-2025-09-10,server-side-fallback-2026-07-01')
            && $request['model'] === 'claude-sonnet-5-5'
            && $request['fallbacks'] === 'default'
            && $request['output_config'] === ['effort' => 'medium']
            && count($request['system']) === 2
            && collect($request['system'])->every(fn (array $block): bool => $block['cache_control'] === ['type' => 'ephemeral'])
            && collect($request['messages'])->pluck('role')->all() === ['user', 'assistant', 'user']
            && str_ends_with($request['messages'][2]['content'][0]['text'], 'What did the Fed do today?')
            && $tools->firstWhere('name', 'web_search')['max_uses'] === 5
            && $tools->pluck('name')->contains('remember');
    });

    expect($reply->refresh()->body)->toBe('Held rates.')
        ->and(Cost::where('purpose', CostPurpose::Reply)->sole())
        ->web_search_requests->toBe(2)
        ->usd->toBe('0.023000');
});

it('answers in Donna\'s voice when the model declines, never with a raw error', function () {
    Queue::fake([EmbedMessage::class, TagTopics::class]);
    donnaExists();
    Embeddings::fake();
    $reply = pendingReplyTo('Something the model will not touch.');

    Http::fake(['api.anthropic.com/v1/messages' => Http::response([
        'id' => 'msg_1',
        'type' => 'message',
        'role' => 'assistant',
        'model' => 'claude-sonnet-5-5',
        'content' => [],
        'stop_reason' => 'refusal',
        'stop_details' => ['type' => 'refusal', 'category' => 'general_harms'],
        'usage' => ['input_tokens' => 500, 'output_tokens' => 0],
    ])]);

    GenerateReply::dispatchSync($reply);

    expect($reply->refresh())
        ->status->toBe(MessageStatus::Complete)
        ->body->toBe('I can\'t help with that one.')
        ->and(Cost::where('purpose', CostPurpose::Reply)->count())->toBe(1);
});

it('marks the reply failed once retries run out, keeping Josh\'s message', function () {
    $reply = pendingReplyTo('Are you there?');

    (new GenerateReply($reply))->failed(new RuntimeException('Overloaded'));

    expect($reply->refresh())
        ->status->toBe(MessageStatus::Failed)
        ->error->toBe('Donna could not reply. Your message is saved; send it again in a moment.')
        ->and($reply->inReplyTo->body)->toBe('Are you there?');
});

it('does not reply twice when the job runs again after finishing', function () {
    Donna::fake()->preventStrayPrompts();
    $reply = Message::factory()->replyTo(Message::factory()->create())->create(['body' => 'Already said.']);

    GenerateReply::dispatchSync($reply);

    Donna::assertNeverPrompted();
    expect($reply->refresh()->body)->toBe('Already said.');
});

it('still replies, from full-text memory, when embeddings are down, and retries the embedding later', function () {
    Queue::fake([EmbedMessage::class, TagTopics::class]);
    Exceptions::fake();
    donnaExists();
    Embeddings::fake(fn () => throw new RuntimeException('Voyage is down.'));
    Donna::fake(['Tuesdays.']);
    $earlier = Message::factory()->create(['body' => 'Kristen dyes yarn on Tuesdays.']);
    config(['donna.context.recent_messages' => 0]);
    $reply = pendingReplyTo('When does Kristen dye yarn?');

    GenerateReply::dispatchSync($reply);

    expect($reply->refresh()->body)->toBe('Tuesdays.');

    Donna::assertPrompted(fn (AgentPrompt $prompt): bool => $prompt->agent->context->memory->exchanges->first()->message->is($earlier));
    Queue::assertPushed(EmbedMessage::class, fn (EmbedMessage $job): bool => $job->message->is($reply->inReplyTo));
    Exceptions::assertReported(RuntimeException::class);
});
