<?php

namespace App\Extraction\Contracts;

use App\Extraction\BatchRequest;
use App\Extraction\BatchResult;

/**
 * The provider's batch API, as much of it as daily extraction needs. Batches
 * are asynchronous and cost half as much as live calls.
 */
interface BatchClient
{
    /**
     * Submit the requests as one batch.
     *
     * @param  list<BatchRequest>  $requests
     * @return string The batch's ID.
     */
    public function submit(array $requests): string;

    /**
     * Determine if the batch has finished processing, successfully or not.
     */
    public function hasEnded(string $batchId): bool;

    /**
     * Get the result of every request in a finished batch, in no particular order.
     *
     * @return list<BatchResult>
     */
    public function results(string $batchId): array;
}
