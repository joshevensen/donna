<?php

use Anthropic\Client;
use App\Extraction\AnthropicBatchClient;
use App\Extraction\BatchRequest;
use GuzzleHttp\Client as Guzzle;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;

/**
 * The real batch client, talking to canned HTTP responses instead of Anthropic.
 *
 * @param  list<Response>  $responses
 * @param  array<int, array<string, mixed>>  $sent  Filled with each request the SDK makes.
 */
function batchClient(array $responses, array &$sent = []): AnthropicBatchClient
{
    $stack = HandlerStack::create(new MockHandler($responses));
    $stack->push(Middleware::history($sent));

    $http = new Guzzle(['handler' => $stack]);

    return new AnthropicBatchClient(new Client(apiKey: 'test-key', requestOptions: [
        'transporter' => $http,
        'streamingTransporter' => $http,
        'maxRetries' => 0,
    ]));
}

function batchJson(string $status): string
{
    return json_encode([
        'id' => 'msgbatch_123',
        'type' => 'message_batch',
        'processing_status' => $status,
        'request_counts' => ['processing' => 0, 'succeeded' => 0, 'errored' => 0, 'canceled' => 0, 'expired' => 0],
        'created_at' => '2026-11-25T09:00:00Z',
        'expires_at' => '2026-11-26T09:00:00Z',
        'ended_at' => null,
        'archived_at' => null,
        'cancel_initiated_at' => null,
        'results_url' => null,
    ]);
}

it('submits each request to the Message Batches API as structured output', function () {
    $sent = [];
    $client = batchClient([new Response(200, ['Content-Type' => 'application/json'], batchJson('in_progress'))], $sent);

    $id = $client->submit([new BatchRequest(
        customId: 'day-2026-11-24',
        model: 'claude-sonnet-5-5',
        system: 'You maintain memory.',
        prompt: 'The conversation.',
        schema: ['type' => 'object', 'properties' => ['facts' => ['type' => 'array']], 'required' => ['facts'], 'additionalProperties' => false],
        maxTokens: 32_000,
        effort: 'medium',
    )]);

    $request = $sent[0]['request'];
    $body = json_decode((string) $request->getBody(), true);

    expect($id)->toBe('msgbatch_123')
        ->and($request->getMethod())->toBe('POST')
        ->and($request->getUri()->getPath())->toBe('/v1/messages/batches')
        ->and($request->getHeaderLine('Authorization'))->toBe('Bearer test-key')
        ->and($body['requests'])->toHaveCount(1)
        ->and($body['requests'][0]['custom_id'])->toBe('day-2026-11-24')
        ->and($body['requests'][0]['params'])->toMatchArray([
            'model' => 'claude-sonnet-5-5',
            'max_tokens' => 32_000,
            'system' => 'You maintain memory.',
            'messages' => [['role' => 'user', 'content' => 'The conversation.']],
        ])
        ->and($body['requests'][0]['params']['output_config'])->toMatchArray(['effort' => 'medium'])
        ->and($body['requests'][0]['params']['output_config']['format'])->toMatchArray([
            'type' => 'json_schema',
            'schema' => ['type' => 'object', 'properties' => ['facts' => ['type' => 'array']], 'required' => ['facts'], 'additionalProperties' => false],
        ]);
});

it('knows a batch has ended only once Anthropic says so', function (string $status, bool $ended) {
    $client = batchClient([new Response(200, ['Content-Type' => 'application/json'], batchJson($status))]);

    expect($client->hasEnded('msgbatch_123'))->toBe($ended);
})->with([
    'still processing' => ['in_progress', false],
    'ended' => ['ended', true],
]);

it('reads results into decoded output with usage, and failures with their reason', function () {
    $message = fn (string $stopReason, string $text): array => [
        'id' => 'msg_1', 'type' => 'message', 'role' => 'assistant', 'model' => 'claude-sonnet-5-5',
        'content' => [['type' => 'text', 'text' => $text, 'citations' => null]],
        'stop_reason' => $stopReason, 'stop_sequence' => null,
        'usage' => ['input_tokens' => 1_000, 'output_tokens' => 200, 'cache_read_input_tokens' => 4_000, 'cache_creation_input_tokens' => 500],
    ];

    $lines = implode("\n", array_map(json_encode(...), [
        ['custom_id' => 'day-2026-11-23', 'result' => ['type' => 'succeeded', 'message' => $message('end_turn', '{"facts":[]}')]],
        ['custom_id' => 'day-2026-11-24', 'result' => ['type' => 'errored', 'error' => ['type' => 'error', 'error' => ['type' => 'overloaded_error', 'message' => 'Overloaded']]]],
        ['custom_id' => 'day-2026-11-25', 'result' => ['type' => 'succeeded', 'message' => $message('max_tokens', '{"facts":[')]],
        ['custom_id' => 'day-2026-11-26', 'result' => ['type' => 'expired']],
    ]));

    $client = batchClient([new Response(200, ['Content-Type' => 'application/x-jsonl'], $lines)]);

    [$succeeded, $errored, $cutOff, $expired] = $client->results('msgbatch_123');

    expect($succeeded)
        ->customId->toBe('day-2026-11-23')
        ->output->toBe(['facts' => []])
        ->model->toBe('claude-sonnet-5-5')
        ->inputTokens->toBe(5_500)
        ->cacheReadTokens->toBe(4_000)
        ->cacheWriteTokens->toBe(500)
        ->outputTokens->toBe(200)
        ->and($errored)
        ->succeeded()->toBeFalse()
        ->error->toBe('Overloaded')
        ->model->toBeNull()
        ->and($cutOff)
        ->succeeded()->toBeFalse()
        ->model->toBe('claude-sonnet-5-5')
        ->outputTokens->toBe(200)
        ->and($expired->succeeded())->toBeFalse();
});
