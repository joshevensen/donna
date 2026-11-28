<?php

namespace App\Listeners;

use App\Ai\Contracts\TracksCost;
use App\Costs\CostRecorder;
use App\Enums\CostPurpose;
use Laravel\Ai\Events\StepCompleted;
use Laravel\Ai\Gateway\StepResponse;
use Laravel\Ai\Responses\Data\ProviderToolCall;

/**
 * An agent run is one model call per step, so each completed step is one
 * Cost — including the steps of a run that later fails.
 */
class RecordStepCost
{
    public function __construct(private CostRecorder $costs) {}

    public function handle(StepCompleted $event): void
    {
        $agent = $event->agent;
        $usage = $event->response->usage;
        $purpose = $agent instanceof TracksCost ? $agent->costPurpose() : CostPurpose::Reply;

        $this->costs->record(
            purpose: $purpose,
            provider: $event->provider->name(),
            // The model that answered, which differs from the one asked after a refusal fallback.
            model: $event->response->meta->model ?: $event->model,
            inputTokens: $usage->inputTokens,
            outputTokens: $usage->outputTokens,
            cacheReadTokens: $usage->cacheReadInputTokens ?? 0,
            cacheWriteTokens: $usage->cacheWriteInputTokens ?? 0,
            webSearchRequests: $this->webSearchRequests($event->response),
            trigger: $agent instanceof TracksCost ? $agent->costTrigger() : null,
            cacheWriteOneHourTokens: $this->cacheWriteOneHourTokens($event, $purpose, $usage->cacheWriteInputTokens ?? 0),
        );
    }

    /**
     * Cache writes on the one-hour cache cost more than five-minute ones.
     * Anthropic reports the split; without it, her replies are assumed to
     * use the TTL they ask for.
     */
    private function cacheWriteOneHourTokens(StepCompleted $event, CostPurpose $purpose, int $written): int
    {
        $reported = $event->response->raw?->json('usage.cache_creation.ephemeral_1h_input_tokens');

        if ($reported !== null) {
            return (int) $reported;
        }

        return $purpose === CostPurpose::Reply && config('donna.models.reply.cache_ttl') === '1h' ? $written : 0;
    }

    /**
     * Searches are billed per request on top of tokens. Anthropic reports the
     * count in usage; the tool-call blocks are the fallback.
     */
    private function webSearchRequests(StepResponse $response): int
    {
        $reported = $response->raw?->json('usage.server_tool_use.web_search_requests');

        if ($reported !== null) {
            return (int) $reported;
        }

        return collect($response->providerToolCalls)
            ->filter(fn (ProviderToolCall $call): bool => $call->type === 'server_tool_use'
                && ($call->data['name'] ?? null) === 'web_search')
            ->count();
    }
}
