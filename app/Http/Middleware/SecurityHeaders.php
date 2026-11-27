<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Vite;
use Symfony\Component\HttpFoundation\Response;

/**
 * Headers for the web app's page: it may only load its own scripts, styles
 * and images, and no other site may put it in a frame.
 */
class SecurityHeaders
{
    /**
     * The built app needs no inline scripts or styles, so none are allowed.
     */
    private const array CONTENT_SECURITY_POLICY = [
        "default-src 'self'",
        "script-src 'self'",
        "style-src 'self'",
        "img-src 'self' data:",
        "connect-src 'self'",
        "manifest-src 'self'",
        "worker-src 'self'",
        "object-src 'none'",
        "base-uri 'self'",
        "form-action 'self'",
        "frame-ancestors 'none'",
    ];

    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $response->headers->set('X-Frame-Options', 'DENY');
        $response->headers->set('X-Content-Type-Options', 'nosniff');

        // Nothing about where Josh is goes to another site. The app's own requests
        // still say where they came from, which is how Sanctum recognises them.
        $response->headers->set('Referrer-Policy', 'same-origin');

        // Vite's dev server injects inline scripts and serves from another port.
        if (! Vite::isRunningHot()) {
            $response->headers->set('Content-Security-Policy', implode('; ', self::CONTENT_SECURITY_POLICY));
        }

        return $response;
    }
}
