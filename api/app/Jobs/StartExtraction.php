<?php

namespace App\Jobs;

use App\Enums\ExtractionRunStatus;
use App\Extraction\Contracts\BatchClient;
use App\Extraction\ExtractionRequests;
use App\Models\ExtractionRun;
use App\Models\Message;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Once a day, sends everything said since the last extraction to the batch
 * API. The very first run reaches back to the first message.
 */
class StartExtraction implements ShouldQueue
{
    use Queueable;

    public function handle(BatchClient $batches, ExtractionRequests $requests): void
    {
        if (ExtractionRun::query()->where('status', ExtractionRunStatus::Submitted)->exists()) {
            return;
        }

        $since = ExtractionRun::query()->where('status', ExtractionRunStatus::Applied)->max('window_end');

        // The last whole second, so nothing still being written falls between two windows.
        $until = now()->startOfSecond()->subSecond();

        $messages = Message::query()
            ->complete()
            ->when($since, fn ($query) => $query->where('created_at', '>', $since))
            ->where('created_at', '<=', $until)
            ->oldest('id')
            ->get();

        if ($messages->isEmpty()) {
            return;
        }

        ExtractionRun::create([
            'window_start' => $since ?? $messages->first()->created_at,
            'window_end' => $until,
            'batch_id' => $batches->submit($requests->for($messages)),
            'status' => ExtractionRunStatus::Submitted,
        ]);
    }
}
