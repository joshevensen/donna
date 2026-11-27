<?php

namespace App\Push;

use App\Models\User;
use Illuminate\Support\Facades\Cache;

/**
 * Whether Josh is looking at the conversation right now. An open app polls
 * every few seconds, and each poll is noted here; a reply that finishes
 * while it is being watched needs no notification. Per user, not per
 * device: an open tab on the Mac holds back the phone's notification too.
 */
class Presence
{
    /**
     * The app just polled: someone is watching.
     */
    public function seen(User $user): void
    {
        Cache::put($this->key($user), true, config('donna.push.open_seconds'));
    }

    /**
     * The app went to the background, so the next reply should notify
     * without waiting for the last poll to age out.
     */
    public function left(User $user): void
    {
        Cache::forget($this->key($user));
    }

    public function isWatching(User $user): bool
    {
        return Cache::has($this->key($user));
    }

    private function key(User $user): string
    {
        return "donna:watching:{$user->id}";
    }
}
