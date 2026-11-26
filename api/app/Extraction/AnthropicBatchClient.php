<?php

namespace App\Extraction;

use Anthropic\Client;
use Anthropic\Messages\Batches\MessageBatchErroredResult;
use Anthropic\Messages\Batches\MessageBatchIndividualResponse;
use Anthropic\Messages\Batches\MessageBatchSucceededResult;
use Anthropic\Messages\JSONOutputFormat;
use Anthropic\Messages\OutputConfig;
use App\Extraction\Contracts\BatchClient;

/**
 * Anthropic's Message Batches API, through its official PHP SDK.
 */
class AnthropicBatchClient implements BatchClient
{
    public function __construct(private Client $client) {}

    public function submit(array $requests): string
    {
        $batch = $this->client->messages->batches->create(
            requests: array_map(fn (BatchRequest $request): array => [
                'customID' => $request->customId,
                'params' => [
                    'model' => $request->model,
                    'maxTokens' => $request->maxTokens,
                    'system' => $request->system,
                    'messages' => [['role' => 'user', 'content' => $request->prompt]],
                    'outputConfig' => OutputConfig::with(
                        effort: $request->effort,
                        format: JSONOutputFormat::with(schema: $request->schema),
                    ),
                ],
            ], $requests),
        );

        return $batch->id;
    }

    public function hasEnded(string $batchId): bool
    {
        return $this->client->messages->batches->retrieve($batchId)->processingStatus === 'ended';
    }

    public function results(string $batchId): array
    {
        $results = [];

        foreach ($this->client->messages->batches->resultsStream($batchId) as $response) {
            $results[] = $this->result($response);
        }

        return $results;
    }

    private function result(MessageBatchIndividualResponse $response): BatchResult
    {
        $result = $response->result;

        if (! $result instanceof MessageBatchSucceededResult) {
            return new BatchResult(
                customId: $response->customID,
                output: null,
                error: $result instanceof MessageBatchErroredResult
                    ? $result->error->error->message
                    : "The request {$result->type}.",
            );
        }

        $message = $result->message;
        $usage = $message->usage;

        $text = '';

        foreach ($message->content as $block) {
            if ($block->type === 'text') {
                $text .= $block->text;
            }
        }

        $output = json_decode($text, associative: true);
        $cacheRead = $usage->cacheReadInputTokens ?? 0;
        $cacheWrite = $usage->cacheCreationInputTokens ?? 0;

        return new BatchResult(
            customId: $response->customID,
            // A refusal or a cut-off answer is paid for but has nothing to apply.
            output: $message->stopReason === 'end_turn' && is_array($output) ? $output : null,
            model: $message->model,
            // Anthropic reports uncached input separately from the cache counts.
            inputTokens: $usage->inputTokens + $cacheRead + $cacheWrite,
            outputTokens: $usage->outputTokens,
            cacheReadTokens: $cacheRead,
            cacheWriteTokens: $cacheWrite,
            error: is_array($output) && $message->stopReason === 'end_turn' ? null : "The model stopped with [{$message->stopReason}].",
        );
    }
}
