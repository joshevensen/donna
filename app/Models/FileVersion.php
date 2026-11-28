<?php

namespace App\Models;

use App\Enums\MessageAuthor;
use Database\Factories\FileVersionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A file's text at one moment. Every change writes a new one: Josh's saves,
 * each of Donna's edits, a revert. Version 1 is what was uploaded or first
 * written and is never edited or removed.
 *
 * `message_id` is the reply Donna made the change in, and `operation_hash`
 * identifies the change itself, so a reply that runs again doesn't repeat it.
 */
#[Fillable(['file_id', 'number', 'body', 'content_hash', 'author', 'message_id', 'operation_hash', 'summary', 'created_at'])]
class FileVersion extends Model
{
    /** @use HasFactory<FileVersionFactory> */
    use HasFactory;

    public const UPDATED_AT = null;

    /**
     * @return BelongsTo<File, $this>
     */
    public function file(): BelongsTo
    {
        return $this->belongsTo(File::class)->withTrashed();
    }

    /**
     * @return BelongsTo<Message, $this>
     */
    public function message(): BelongsTo
    {
        return $this->belongsTo(Message::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'author' => MessageAuthor::class,
            'number' => 'integer',
            'created_at' => 'datetime',
        ];
    }
}
