<?php

namespace App\Models;

use App\Enums\FileExtraction;
use App\Enums\FileKind;
use Database\Factories\FileFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A document that belongs to Donna, not to a message. Every file starts
 * working: stored, visible, usable in the conversation. A kept file is also
 * split into chunks and found on its own (Archive Memory).
 *
 * Text lives in versions; the original bytes of a PDF or image live in
 * storage and are never changed. Deleting is soft: Donna can bring a file back.
 */
#[Fillable([
    'name', 'kind', 'mime', 'size_bytes', 'storage_path', 'current_version_id',
    'kept_at', 'extraction', 'extraction_error', 'meta',
])]
class File extends Model
{
    /** @use HasFactory<FileFactory> */
    use HasFactory, SoftDeletes;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'extraction' => 'ready',
    ];

    /**
     * @return HasMany<FileVersion, $this>
     */
    public function versions(): HasMany
    {
        return $this->hasMany(FileVersion::class);
    }

    /**
     * @return BelongsTo<FileVersion, $this>
     */
    public function currentVersion(): BelongsTo
    {
        return $this->belongsTo(FileVersion::class, 'current_version_id');
    }

    /**
     * @return HasMany<FileChunk, $this>
     */
    public function chunks(): HasMany
    {
        return $this->hasMany(FileChunk::class);
    }

    /**
     * The messages this file was attached to, or created, edited or opened in.
     *
     * @return BelongsToMany<Message, $this>
     */
    public function messages(): BelongsToMany
    {
        return $this->belongsToMany(Message::class)->withPivot('role');
    }

    /**
     * @return MorphToMany<Person, $this>
     */
    public function people(): MorphToMany
    {
        return $this->morphedByMany(Person::class, 'subject', 'file_subjects');
    }

    /**
     * @return MorphToMany<Project, $this>
     */
    public function projects(): MorphToMany
    {
        return $this->morphedByMany(Project::class, 'subject', 'file_subjects');
    }

    /**
     * @return MorphToMany<Topic, $this>
     */
    public function topics(): MorphToMany
    {
        return $this->morphedByMany(Topic::class, 'subject', 'file_subjects');
    }

    /**
     * The names of everything the file is linked to: people, then projects, then topics.
     *
     * @return list<array{type: string, name: string}>
     */
    public function subjects(): array
    {
        return [
            ...$this->people->map(fn (Person $person): array => ['type' => 'person', 'name' => $person->name]),
            ...$this->projects->map(fn (Project $project): array => ['type' => 'project', 'name' => $project->name]),
            ...$this->topics->map(fn (Topic $topic): array => ['type' => 'topic', 'name' => $topic->name]),
        ];
    }

    public function isKept(): bool
    {
        return $this->kept_at !== null;
    }

    /**
     * Whether Josh can edit it in the viewer: markdown, and small enough to hand to a textarea.
     */
    public function isEditable(): bool
    {
        return $this->kind->isEditable() && $this->size_bytes <= config('donna.files.viewer_bytes');
    }

    /**
     * The current text, or null for a file that has none.
     */
    public function body(): ?string
    {
        return $this->currentVersion?->body;
    }

    /**
     * Where the smaller copy of an image is, when one was made.
     */
    public function previewPath(): ?string
    {
        return $this->meta['preview_path'] ?? null;
    }

    /**
     * @param  Builder<File>  $query
     */
    #[Scope]
    protected function kept(Builder $query): void
    {
        $query->whereNotNull($query->qualifyColumn('kept_at'));
    }

    /**
     * @param  Builder<File>  $query
     */
    #[Scope]
    protected function working(Builder $query): void
    {
        $query->whereNull($query->qualifyColumn('kept_at'));
    }

    /**
     * Files that are part of the conversation: kept, or tied to a message.
     * An upload that was never sent with a message is neither listed nor counted.
     *
     * @param  Builder<File>  $query
     */
    #[Scope]
    protected function inUse(Builder $query): void
    {
        $query->where(fn (Builder $query) => $query
            ->whereNotNull($query->qualifyColumn('kept_at'))
            ->orWhereHas('messages'));
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'kind' => FileKind::class,
            'extraction' => FileExtraction::class,
            'size_bytes' => 'integer',
            'kept_at' => 'datetime',
            'meta' => 'array',
        ];
    }
}
