<?php

namespace App\Models;

use App\Enums\ExtractionRunStatus;
use Database\Factories\ExtractionRunFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;

#[Fillable(['window_start', 'window_end', 'batch_id', 'status', 'results_applied_at', 'error'])]
class ExtractionRun extends Model
{
    /** @use HasFactory<ExtractionRunFactory> */
    use HasFactory;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => 'submitted',
    ];

    /**
     * @return MorphMany<Cost, $this>
     */
    public function costs(): MorphMany
    {
        return $this->morphMany(Cost::class, 'trigger');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'window_start' => 'datetime',
            'window_end' => 'datetime',
            'status' => ExtractionRunStatus::class,
            'results_applied_at' => 'datetime',
        ];
    }
}
