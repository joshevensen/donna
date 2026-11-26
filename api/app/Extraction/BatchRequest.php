<?php

namespace App\Extraction;

/**
 * One structured-output request inside a batch.
 */
final readonly class BatchRequest
{
    /**
     * @param  string  $customId  How the result is matched back to this request.
     * @param  array<string, mixed>  $schema  The JSON schema the answer must follow.
     */
    public function __construct(
        public string $customId,
        public string $model,
        public string $system,
        public string $prompt,
        public array $schema,
        public int $maxTokens,
        public string $effort,
    ) {}
}
