<?php

namespace App\Auth;

use App\Models\LoginCode;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Issues and redeems sign-in codes. There are no passwords: a code or its
 * link, sent to Josh's own address, is the only way into the web app.
 */
class LoginCodes
{
    /**
     * Make a fresh code and link for the user. Any earlier one stops working.
     */
    public function issue(User $user): IssuedLoginCode
    {
        $code = str_pad((string) random_int(0, 999_999), 6, '0', STR_PAD_LEFT);
        $token = Str::random(40);

        $user->loginCodes()->delete();

        $login = $user->loginCodes()->create([
            'code_hash' => Hash::make($code),
            'link_token_hash' => $this->hashToken($token),
            'expires_at' => now()->addMinutes(config('donna.login.code_minutes')),
        ]);

        return new IssuedLoginCode(
            code: $code,
            url: url("/login/link/{$token}"),
            expiresAt: $login->expires_at,
        );
    }

    /**
     * Spend the user's code. A wrong guess counts against it, and enough of
     * them kill it.
     */
    public function redeemCode(User $user, string $code): bool
    {
        $login = $user->loginCodes()->usable()->latest('id')->first();

        if ($login === null) {
            return false;
        }

        if (! Hash::check($code, $login->code_hash)) {
            $login->increment('attempts');

            return false;
        }

        return $this->spend($login);
    }

    /**
     * Spend the code behind a link token and return who it signs in.
     */
    public function redeemLink(string $token): ?User
    {
        $login = LoginCode::query()
            ->usable()
            ->where('link_token_hash', $this->hashToken($token))
            ->first();

        return $login !== null && $this->spend($login) ? $login->user : null;
    }

    /**
     * Mark the code used. Only one of two simultaneous attempts wins.
     */
    private function spend(LoginCode $login): bool
    {
        return LoginCode::query()
            ->whereKey($login->id)
            ->whereNull('used_at')
            ->update(['used_at' => now()]) === 1;
    }

    /**
     * Link tokens are long and random, so a fast hash is enough and lets the
     * row be found by it. The short code gets a slow one.
     */
    private function hashToken(string $token): string
    {
        return hash('sha256', $token);
    }
}
