<?php

namespace App\Models;

use App\Enums\CharacterProfileStatus;
use Database\Factories\CharacterProfileFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Who Donna is, versioned. Exactly one version is active at a time, and a
 * version only becomes active when Josh approves it.
 */
#[Fillable(['version', 'body', 'status', 'change_summary', 'proposed_in_message_id', 'decided_at'])]
class CharacterProfile extends Model
{
    /** @use HasFactory<CharacterProfileFactory> */
    use HasFactory;

    /**
     * The profile Donna is currently speaking as.
     */
    public static function current(): self
    {
        return static::query()->where('status', CharacterProfileStatus::Active)->sole();
    }

    /**
     * @return BelongsTo<Message, $this>
     */
    public function proposedInMessage(): BelongsTo
    {
        return $this->belongsTo(Message::class, 'proposed_in_message_id');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => CharacterProfileStatus::class,
            'decided_at' => 'datetime',
        ];
    }
}
