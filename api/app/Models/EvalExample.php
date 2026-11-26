<?php

namespace App\Models;

use App\Enums\EvalRating;
use Database\Factories\EvalExampleFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['message_id', 'rating', 'note'])]
class EvalExample extends Model
{
    /** @use HasFactory<EvalExampleFactory> */
    use HasFactory;

    /**
     * The reply of Donna's that Josh flagged.
     *
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
            'rating' => EvalRating::class,
        ];
    }
}
