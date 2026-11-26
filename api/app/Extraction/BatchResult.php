<?php

namespace App\Extraction;

/**
 * The outcome of one request in a batch.
 */
final readonly class BatchResult
{
    /**
     * @param  array<string, mixed>|null  $output  The decoded structured answer, or null when the request did not succeed.
     * @param  int  $inputTokens  The whole prompt, including the cache counts.
     */
    public function __construct(
        public string $customId,
        public ?array $output,
        public ?string $model = null,
        public int $inputTokens = 0,
        public int $outputTokens = 0,
        public int $cacheReadTokens = 0,
        public int $cacheWriteTokens = 0,
        public ?string $error = null,
    ) {}

    public function succeeded(): bool
    {
        return $this->output !== null;
    }
}
