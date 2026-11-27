<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Auth\LoginCodes;
use App\Http\Controllers\Controller;
use App\Http\Requests\RequestLoginCodeRequest;
use App\Models\User;
use App\Notifications\LoginCodeNotification;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Str;

class LoginCodeController extends Controller
{
    /**
     * Email a sign-in code and link. The answer is the same whether or not
     * the address is Josh's, so it never confirms which address is.
     */
    public function store(RequestLoginCodeRequest $request, LoginCodes $codes): JsonResponse
    {
        $user = User::query()
            ->whereRaw('lower(email) = ?', [Str::lower($request->validated('email'))])
            ->first();

        if ($user !== null) {
            // A mail failure is logged, not shown: `donna:login` is the way in when mail is down.
            rescue(fn () => $user->notify(new LoginCodeNotification($codes->issue($user))));
        }

        return response()->json(status: 202);
    }
}
