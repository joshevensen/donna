<?php

namespace App\Models;

use Database\Factories\PushSubscriptionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use NotificationChannels\WebPush\PushSubscription as WebPushSubscription;

/**
 * One browser or installed app that asked for notifications. Extends the
 * push package's model so its channel can send to it and delete it when
 * the push service says the endpoint is gone.
 */
class PushSubscription extends WebPushSubscription
{
    /** @use HasFactory<PushSubscriptionFactory> */
    use HasFactory;

    /**
     * @var string
     */
    protected $table = 'push_subscriptions';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'endpoint',
        'public_key',
        'auth_token',
        'content_encoding',
        'user_agent',
        'last_used_at',
    ];

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            ...$this->casts,
            'last_used_at' => 'datetime',
        ];
    }
}
