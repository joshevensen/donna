<?php

namespace App\Http\Middleware;

use App\Auth\Sessions;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Ends a web session once it is older than the absolute cap, however
 * recently it was used. The idle timeout alone never stops a stolen session
 * that someone keeps using.
 */
class EndExpiredSessions
{
    public function __construct(private Sessions $sessions) {}

    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->hasSession() && Auth::guard('web')->check() && $this->sessions->pastCap($request)) {
            // Signed out here, the request carries on as a guest: protected routes answer 401.
            $this->sessions->end($request);
        }

        return $next($request);
    }
}
