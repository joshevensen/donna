<?php

namespace App\Models;

use App\Enums\ProjectStatus;
use App\Enums\ProjectTier;
use Database\Factories\ProjectFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\MorphToMany;

#[Fillable(['name', 'aliases', 'owner_person_id', 'tier', 'status', 'summary', 'status_changed_at'])]
class Project extends Model
{
    /** @use HasFactory<ProjectFactory> */
    use HasFactory;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'aliases' => '[]',
        'status' => 'proposed',
    ];

    /**
     * @return BelongsTo<Person, $this>
     */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(Person::class, 'owner_person_id');
    }

    /**
     * @return BelongsToMany<Topic, $this>
     */
    public function topics(): BelongsToMany
    {
        return $this->belongsToMany(Topic::class);
    }

    /**
     * @return MorphToMany<Fact, $this>
     */
    public function facts(): MorphToMany
    {
        return $this->morphToMany(Fact::class, 'subject', 'fact_subjects');
    }

    /**
     * Every name this project goes by, canonical name first.
     *
     * @return list<string>
     */
    public function names(): array
    {
        return array_values(array_unique([$this->name, ...$this->aliases]));
    }

    /**
     * Projects Josh has agreed to track and has not dropped.
     *
     * @param  Builder<Project>  $query
     */
    #[Scope]
    protected function tracked(Builder $query): void
    {
        $query->whereIn('status', [ProjectStatus::Active, ProjectStatus::Parked]);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'aliases' => 'array',
            'tier' => ProjectTier::class,
            'status' => ProjectStatus::class,
            'status_changed_at' => 'datetime',
        ];
    }
}
