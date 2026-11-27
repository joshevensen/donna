<?php

use App\Http\Middleware\EndExpiredSessions;
use Illuminate\Contracts\Auth\Middleware\AuthenticatesRequests;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // The web app signs in with a cookie session; the terminal keeps its token.
        $middleware->statefulApi();

        // The session's age is checked once the session has started and before
        // anything decides who is signed in.
        $middleware->api(append: [EndExpiredSessions::class]);
        $middleware->prependToPriorityList(AuthenticatesRequests::class, EndExpiredSessions::class);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
