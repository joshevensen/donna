<?php

namespace App\Models;

use App\Enums\PersonKind;
use Database\Factories\PersonFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphToMany;

#[Fillable(['kind', 'name', 'aliases', 'relationship', 'summary', 'user_id'])]
class Person extends Model
{
    /** @use HasFactory<PersonFactory> */
    use HasFactory;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'kind' => 'other',
        'aliases' => '[]',
    ];

    /**
     * Josh's own Person, the subject of Personal Memory.
     */
    public static function josh(): self
    {
        return static::query()->where('kind', PersonKind::Self)->sole();
    }

    /**
     * Donna's Person, which her shared history and opinions hang on.
     */
    public static function donna(): self
    {
        return static::query()->where('kind', PersonKind::Donna)->sole();
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return MorphToMany<Fact, $this>
     */
    public function facts(): MorphToMany
    {
        return $this->morphToMany(Fact::class, 'subject', 'fact_subjects');
    }

    /**
     * Every name this person goes by, canonical name first.
     *
     * @return list<string>
     */
    public function names(): array
    {
        return array_values(array_unique([$this->name, ...$this->aliases]));
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'kind' => PersonKind::class,
            'aliases' => 'array',
        ];
    }
}
