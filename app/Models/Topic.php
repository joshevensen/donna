<?php

namespace App\Models;

use Database\Factories\TopicFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Casts\AsVector;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\MorphToMany;

#[Fillable(['name', 'summary', 'first_discussed_at', 'last_discussed_at', 'embedding'])]
#[Hidden(['embedding'])]
class Topic extends Model
{
    /** @use HasFactory<TopicFactory> */
    use HasFactory;

    /**
     * @return BelongsToMany<Message, $this>
     */
    public function messages(): BelongsToMany
    {
        return $this->belongsToMany(Message::class);
    }

    /**
     * @return BelongsToMany<Project, $this>
     */
    public function projects(): BelongsToMany
    {
        return $this->belongsToMany(Project::class);
    }

    /**
     * @return MorphToMany<Fact, $this>
     */
    public function facts(): MorphToMany
    {
        return $this->morphToMany(Fact::class, 'subject', 'fact_subjects');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'first_discussed_at' => 'datetime',
            'last_discussed_at' => 'datetime',
            'embedding' => AsVector::class,
        ];
    }
}
