<?php

namespace App\Models;

use App\Enums\InterviewAreaStatus;
use Database\Factories\InterviewAreaFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['area', 'status', 'notes', 'last_touched_at'])]
class InterviewArea extends Model
{
    /** @use HasFactory<InterviewAreaFactory> */
    use HasFactory;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => 'open',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => InterviewAreaStatus::class,
            'last_touched_at' => 'datetime',
        ];
    }
}
