<?php

use App\Http\Middleware\SecurityHeaders;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| The web app
|--------------------------------------------------------------------------
|
| One view at every address that isn't the API, Sanctum's CSRF cookie, the
| health check or a built asset. React Router decides what to show from
| there. Files in public/ (the service worker, the manifest, the icons) are
| served by the web server before a request gets here.
|
*/

Route::view('/{path?}', 'app')
    ->where('path', '^(?!(api|sanctum|build)(/|$)|up$).*')
    ->middleware(SecurityHeaders::class)
    ->name('app');
