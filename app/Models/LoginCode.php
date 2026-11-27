<?php

namespace App\Models;

use Database\Factories\LoginCodeFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One emailed sign-in: a 6-digit code and a link token, both stored hashed.
 * Either one signs in, once, and using one spends the other.
 */
#[Fillable(['user_id', 'code_hash', 'link_token_hash', 'expires_at', 'used_at', 'attempts'])]
#[Hidden(['code_hash', 'link_token_hash'])]
class LoginCode extends Model
{
    /** @use HasFactory<LoginCodeFactory> */
    use HasFactory;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'attempts' => 0,
    ];

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Codes that can still sign someone in: unused, unexpired, and not
     * guessed at too many times.
     *
     * @param  Builder<LoginCode>  $query
     */
    #[Scope]
    protected function usable(Builder $query): void
    {
        $query
            ->whereNull('used_at')
            ->where('expires_at', '>', now())
            ->where('attempts', '<', config('donna.login.max_attempts'));
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'used_at' => 'datetime',
        ];
    }
}
