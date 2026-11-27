<?php

namespace App\Models;

use App\Enums\FactKind;
use App\Enums\FactProvenance;
use Database\Factories\FactFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\AsVector;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphToMany;

/**
 * One thing Donna knows. Never edited in place: a correction creates a new
 * Fact and marks this one superseded, so the history of what Josh used to
 * think survives. "Active" means not superseded.
 */
#[Fillable([
    'statement', 'kind', 'confidence', 'provenance', 'source_message_id', 'superseded_by_id',
    'superseded_at', 'notable_change', 'retrieval_count', 'last_retrieved_at', 'embedding',
])]
#[Hidden(['embedding', 'search'])]
class Fact extends Model
{
    /** @use HasFactory<FactFactory> */
    use HasFactory;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'kind' => 'fact',
        'confidence' => 1,
        'notable_change' => false,
        'retrieval_count' => 0,
    ];

    /**
     * @return MorphToMany<Person, $this>
     */
    public function people(): MorphToMany
    {
        return $this->morphedByMany(Person::class, 'subject', 'fact_subjects');
    }

    /**
     * @return MorphToMany<Project, $this>
     */
    public function projects(): MorphToMany
    {
        return $this->morphedByMany(Project::class, 'subject', 'fact_subjects');
    }

    /**
     * @return MorphToMany<Topic, $this>
     */
    public function topics(): MorphToMany
    {
        return $this->morphedByMany(Topic::class, 'subject', 'fact_subjects');
    }

    /**
     * @return BelongsTo<Message, $this>
     */
    public function sourceMessage(): BelongsTo
    {
        return $this->belongsTo(Message::class, 'source_message_id');
    }

    /**
     * The newer Fact that replaced this one.
     *
     * @return BelongsTo<Fact, $this>
     */
    public function supersededBy(): BelongsTo
    {
        return $this->belongsTo(self::class, 'superseded_by_id');
    }

    /**
     * The older Facts this one replaced.
     *
     * @return HasMany<Fact, $this>
     */
    public function predecessors(): HasMany
    {
        return $this->hasMany(self::class, 'superseded_by_id');
    }

    public function isActive(): bool
    {
        return $this->superseded_at === null;
    }

    /**
     * @param  Builder<Fact>  $query
     */
    #[Scope]
    protected function active(Builder $query): void
    {
        $query->whereNull($query->qualifyColumn('superseded_at'));
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'kind' => FactKind::class,
            'provenance' => FactProvenance::class,
            'confidence' => 'float',
            'superseded_at' => 'datetime',
            'notable_change' => 'boolean',
            'last_retrieved_at' => 'datetime',
            'embedding' => AsVector::class,
        ];
    }
}
