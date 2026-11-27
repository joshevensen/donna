<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Auth\LoginCodes;
use App\Auth\Sessions;
use App\Http\Controllers\Controller;
use App\Http\Requests\VerifyLoginCodeRequest;
use App\Http\Requests\VerifyLoginLinkRequest;
use App\Http\Resources\MeResource;
use App\Models\Person;
use App\Models\User;
use App\Notifications\NewSignInNotification;
use App\Support\UserAgent;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class SessionController extends Controller
{
    public function __construct(private Sessions $sessions) {}

    /**
     * Sign in with the emailed code.
     */
    public function store(VerifyLoginCodeRequest $request, LoginCodes $codes): MeResource
    {
        $this->ensureSession($request);

        $user = User::query()
            ->whereRaw('lower(email) = ?', [Str::lower($request->validated('email'))])
            ->first();

        if ($user === null || ! $codes->redeemCode($user, $request->validated('code'))) {
            throw ValidationException::withMessages([
                'code' => "That code didn't work. Check it, or ask for a new one.",
            ]);
        }

        return $this->signIn($request, $user);
    }

    /**
     * Sign in with the emailed link's token.
     */
    public function storeFromLink(VerifyLoginLinkRequest $request, LoginCodes $codes): MeResource
    {
        $this->ensureSession($request);

        $user = $codes->redeemLink($request->validated('token'));

        if ($user === null) {
            throw ValidationException::withMessages([
                'token' => 'That link has expired or was already used. Ask for a new one.',
            ]);
        }

        return $this->signIn($request, $user);
    }

    /**
     * Sign this device out.
     */
    public function destroy(Request $request): Response
    {
        $this->sessions->end($request);

        return response()->noContent();
    }

    /**
     * Sign every device out.
     */
    public function destroyAll(Request $request): Response
    {
        $this->sessions->endAll($request, $request->user());

        return response()->noContent();
    }

    /**
     * Sign every device out from the "new sign-in" email, without being
     * signed in. The route only lets a validly signed, unexpired link through.
     */
    public function revoke(Request $request): Response
    {
        $this->sessions->endAll($request, Person::josh()->user);

        return response()->noContent();
    }

    /**
     * Cookie sessions only exist for requests that come from the web app
     * itself. Checked before a code is looked at, so a request that could
     * never be signed in doesn't use one up.
     */
    private function ensureSession(Request $request): void
    {
        abort_unless($request->hasSession(), 400, 'Sign in from the web app.');
    }

    private function signIn(Request $request, User $user): MeResource
    {
        $this->sessions->start($request, $user);

        // The sign-in stands even if the email about it can't be sent.
        rescue(fn () => $user->notify(new NewSignInNotification(
            device: UserAgent::describe($request->userAgent()),
            ip: $request->ip(),
            at: now(),
            revokeUrl: $this->revokeUrl(),
        )));

        return new MeResource($user);
    }

    /**
     * The web app's page for the signed revoke request. Its token is the
     * signed URL's expiry and signature, which the page posts back.
     */
    private function revokeUrl(): string
    {
        $expires = now()->addDays(config('donna.login.revoke_link_days'));

        parse_str((string) parse_url(
            URL::temporarySignedRoute('api.v1.auth.revoke', $expires, absolute: false),
            PHP_URL_QUERY,
        ), $query);

        return url("/security/revoke/{$query['expires']}-{$query['signature']}");
    }
}
