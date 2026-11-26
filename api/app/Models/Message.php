<?php

namespace App\Models;

use App\Enums\MessageAuthor;
use App\Enums\MessageSource;
use App\Enums\MessageStatus;
use Database\Factories\MessageFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\AsVector;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphMany;

#[Fillable(['author', 'body', 'source', 'status', 'in_reply_to_id', 'error', 'meta', 'embedding'])]
#[Hidden(['embedding', 'search'])]
class Message extends Model
{
    /** @use HasFactory<MessageFactory> */
    use HasFactory;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'source' => 'terminal',
        'status' => 'complete',
    ];

    /**
     * The message this one answers. Only Donna's replies have one.
     *
     * @return BelongsTo<Message, $this>
     */
    public function inReplyTo(): BelongsTo
    {
        return $this->belongsTo(self::class, 'in_reply_to_id');
    }

    /**
     * Donna's reply to this message.
     *
     * @return HasOne<Message, $this>
     */
    public function reply(): HasOne
    {
        return $this->hasOne(self::class, 'in_reply_to_id');
    }

    /**
     * @return BelongsToMany<Topic, $this>
     */
    public function topics(): BelongsToMany
    {
        return $this->belongsToMany(Topic::class);
    }

    /**
     * @return HasOne<EvalExample, $this>
     */
    public function evalExample(): HasOne
    {
        return $this->hasOne(EvalExample::class);
    }

    /**
     * @return MorphMany<Cost, $this>
     */
    public function costs(): MorphMany
    {
        return $this->morphMany(Cost::class, 'trigger');
    }

    public function isFromDonna(): bool
    {
        return $this->author === MessageAuthor::Donna;
    }

    public function isPending(): bool
    {
        return $this->status === MessageStatus::Pending;
    }

    /**
     * Messages that are part of the conversation as it was actually said:
     * pending and failed replies have no words yet.
     *
     * @param  Builder<Message>  $query
     */
    #[Scope]
    protected function complete(Builder $query): void
    {
        $query->where('status', MessageStatus::Complete);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'author' => MessageAuthor::class,
            'source' => MessageSource::class,
            'status' => MessageStatus::class,
            'meta' => 'array',
            'embedding' => AsVector::class,
        ];
    }
}
