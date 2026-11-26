<?php

namespace App\Models;

use App\Enums\CostPurpose;
use Database\Factories\CostFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * One model call. `input_tokens` is the whole prompt; the cache columns are
 * subsets of it, the way the AI SDK reports them.
 */
#[Fillable([
    'purpose', 'provider', 'model', 'input_tokens', 'output_tokens', 'cache_read_tokens',
    'cache_write_tokens', 'web_search_requests', 'batched', 'usd', 'trigger_type', 'trigger_id',
])]
class Cost extends Model
{
    /** @use HasFactory<CostFactory> */
    use HasFactory;

    /**
     * What caused the call: a Message or an ExtractionRun.
     *
     * @return MorphTo<Model, $this>
     */
    public function trigger(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'purpose' => CostPurpose::class,
            'batched' => 'boolean',
            'usd' => 'decimal:6',
        ];
    }
}
