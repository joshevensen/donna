<?php

use App\Jobs\PollExtractions;
use App\Jobs\StartExtraction;
use Illuminate\Support\Facades\Schedule;

/*
|--------------------------------------------------------------------------
| Daily extraction
|--------------------------------------------------------------------------
|
| While Josh sleeps, the day's conversation goes to the batch API. Batches
| take anywhere from minutes to a day, so a second job checks in on them.
|
*/

Schedule::job(new StartExtraction)
    ->dailyAt(config('donna.extraction.at'))
    ->timezone(config('donna.timezone'));

Schedule::job(new PollExtractions)
    ->cron('*/'.config('donna.extraction.poll_minutes').' * * * *')
    ->withoutOverlapping(30);
