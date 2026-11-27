<?php

namespace App\Auth;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * The web app's cookie sessions: starting one, ending one, ending them all.
 * API tokens (the terminal) are separate and never touched here.
 */
class Sessions
{
    /**
     * The session key holding when this device signed in, for the absolute cap.
     */
    public const string SIGNED_IN_AT = 'signed_in_at';

    /**
     * Sign the user in on this device.
     */
    public function start(Request $request, User $user): void
    {
        Auth::guard('web')->login($user);

        $request->session()->regenerate();
        $request->session()->put(self::SIGNED_IN_AT, now()->getTimestamp());
    }

    /**
     * Sign this device out.
     */
    public function end(Request $request): void
    {
        Auth::guard('web')->logout();

        // The API guard may already have looked the user up for this request.
        Auth::guard('sanctum')->forgetUser();

        if ($request->hasSession()) {
            $request->session()->invalidate();
            $request->session()->regenerateToken();
        }
    }

    /**
     * Sign every device out and stop notifying them. Sessions live in the
     * database, so deleting the user's rows is what ends them.
     */
    public function endAll(Request $request, User $user): void
    {
        // This device first, or its session would be written back when the request ends.
        $this->end($request);

        DB::table(config('session.table'))->where('user_id', $user->id)->delete();

        $user->pushSubscriptions()->delete();
    }

    /**
     * Determine if the session is past the absolute cap, however recently it was used.
     */
    public function pastCap(Request $request): bool
    {
        $signedInAt = $request->session()->get(self::SIGNED_IN_AT);

        return ! is_int($signedInAt)
            || $signedInAt < now()->subDays(config('donna.login.session_max_days'))->getTimestamp();
    }
}
