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

        $this->costs->record(
            purpose: $agent instanceof TracksCost ? $agent->costPurpose() : CostPurpose::Reply,
            provider: $event->provider->name(),
            // The model that answered, which differs from the one asked after a refusal fallback.
            model: $event->response->meta->model ?: $event->model,
            inputTokens: $usage->inputTokens,
            outputTokens: $usage->outputTokens,
            cacheReadTokens: $usage->cacheReadInputTokens ?? 0,
            cacheWriteTokens: $usage->cacheWriteInputTokens ?? 0,
            webSearchRequests: $this->webSearchRequests($event->response),
            trigger: $agent instanceof TracksCost ? $agent->costTrigger() : null,
        );
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
