<?php

namespace App\Jobs;

use App\Costs\CostRecorder;
use App\Enums\CostPurpose;
use App\Enums\ExtractionRunStatus;
use App\Extraction\BatchResult;
use App\Extraction\Contracts\BatchClient;
use App\Extraction\ExtractionApplier;
use App\Models\ExtractionRun;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Throwable;

/**
 * Checks on submitted extraction batches and, once one has ended, writes
 * its results into memory.
 */
class PollExtractions implements ShouldQueue
{
    use Queueable;

    public function handle(BatchClient $batches, ExtractionApplier $applier, CostRecorder $costs): void
    {
        $runs = ExtractionRun::query()->where('status', ExtractionRunStatus::Submitted)->oldest('id')->get();

        foreach ($runs as $run) {
            if (! $batches->hasEnded($run->batch_id)) {
                continue;
            }

            $results = collect($batches->results($run->batch_id))->sortBy('customId')->values();

            // The money is spent whether or not the results can be applied, and
            // it is recorded once even if applying them takes a second poll.
            if (! $run->costs()->where('purpose', CostPurpose::Extraction)->exists()) {
                $this->recordCosts($run, $results, $costs);
            }

            $this->apply($run, $results, $applier);
        }
    }

    /**
     * Apply the run's days in order, all in one transaction. If a day did
     * not come back, the run ends just before it and tomorrow's run picks up
     * from there, so nothing is skipped and nothing is extracted twice.
     *
     * @param  Collection<int, BatchResult>  $results
     */
    private function apply(ExtractionRun $run, Collection $results, ExtractionApplier $applier): void
    {
        $applicable = $results->takeWhile(fn (BatchResult $result): bool => $result->succeeded());
        $firstFailure = $results->first(fn (BatchResult $result): bool => ! $result->succeeded());

        if ($firstFailure !== null) {
            report(new RuntimeException("Extraction run {$run->id}: {$firstFailure->customId} failed — {$firstFailure->error}"));
        }

        if ($applicable->isEmpty()) {
            $run->update(['status' => ExtractionRunStatus::Failed, 'error' => $firstFailure?->error ?? 'The batch returned no results.']);

            return;
        }

        // Model calls happen out here. If embeddings are down this throws, the
        // run stays submitted, and the next poll tries again.
        $embeddings = $applicable->map(fn (BatchResult $result): Collection => $applier->prepare($run, $result->output));

        try {
            DB::transaction(function () use ($run, $applicable, $embeddings, $firstFailure, $applier): void {
                foreach ($applicable as $index => $result) {
                    $applier->apply($run, $result->output, $embeddings[$index]);
                }

                $run->update([
                    'status' => ExtractionRunStatus::Applied,
                    'results_applied_at' => now(),
                    'window_end' => $firstFailure === null ? $run->window_end : $this->justBefore($firstFailure),
                    'error' => $firstFailure?->error,
                ]);
            });
        } catch (Throwable $exception) {
            report($exception);

            $run->update(['status' => ExtractionRunStatus::Failed, 'error' => str($exception->getMessage())->limit(500)->value()]);
        }
    }

    /**
     * The last second before the failed request's day began, in Josh's timezone.
     */
    private function justBefore(BatchResult $failure): CarbonImmutable
    {
        return CarbonImmutable::parse(str($failure->customId)->after('day-')->value(), config('donna.timezone'))
            ->startOfDay()
            ->subSecond()
            ->utc();
    }

    /**
     * @param  Collection<int, BatchResult>  $results
     */
    private function recordCosts(ExtractionRun $run, Collection $results, CostRecorder $costs): void
    {
        foreach ($results as $result) {
            if ($result->model === null) {
                continue;
            }

            $costs->record(
                purpose: CostPurpose::Extraction,
                provider: config('donna.models.extraction.provider'),
                model: $result->model,
                inputTokens: $result->inputTokens,
                outputTokens: $result->outputTokens,
                cacheReadTokens: $result->cacheReadTokens,
                cacheWriteTokens: $result->cacheWriteTokens,
                batched: true,
                trigger: $run,
            );
        }
    }
}
