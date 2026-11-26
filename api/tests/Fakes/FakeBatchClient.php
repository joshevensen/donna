<?php

namespace Tests\Fakes;

use App\Extraction\BatchRequest;
use App\Extraction\BatchResult;
use App\Extraction\Contracts\BatchClient;

/**
 * An in-memory batch API. A batch is "processing" until a test finishes it.
 */
class FakeBatchClient implements BatchClient
{
    /**
     * Every batch submitted, keyed by its ID.
     *
     * @var array<string, list<BatchRequest>>
     */
    public array $submitted = [];

    /**
     * @var array<string, list<BatchResult>>
     */
    private array $finished = [];

    public function submit(array $requests): string
    {
        $id = 'msgbatch_fake_'.(count($this->submitted) + 1);

        $this->submitted[$id] = $requests;

        return $id;
    }

    public function hasEnded(string $batchId): bool
    {
        return isset($this->finished[$batchId]);
    }

    public function results(string $batchId): array
    {
        return $this->finished[$batchId];
    }

    /**
     * End a batch with the given results.
     *
     * @param  list<BatchResult>  $results
     */
    public function finish(string $batchId, array $results): void
    {
        $this->finished[$batchId] = $results;
    }
}
