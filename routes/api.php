<?php

use App\Http\Controllers\Api\V1\Auth\LoginCodeController;
use App\Http\Controllers\Api\V1\Auth\SessionController;
use App\Http\Controllers\Api\V1\MessageController;
use App\Http\Controllers\Api\V1\MessageEvaluationController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->name('api.v1.')->group(function () {
    /*
    |--------------------------------------------------------------------------
    | Signing in to the web app
    |--------------------------------------------------------------------------
    |
    | Cookie sessions for the web app. The terminal's tokens come from
    | `donna:token` and never pass through here.
    |
    */

    Route::prefix('auth')->name('auth.')->group(function () {
        Route::post('login-code', [LoginCodeController::class, 'store'])
            ->middleware('throttle:login-code')
            ->name('login-code');

        Route::middleware('throttle:sign-in')->group(function () {
            Route::post('verify', [SessionController::class, 'store'])->name('verify');
            Route::post('verify-link', [SessionController::class, 'storeFromLink'])->name('verify-link');
            Route::post('revoke', [SessionController::class, 'revoke'])->middleware('signed:relative')->name('revoke');
        });

        Route::middleware('auth:sanctum')->group(function () {
            Route::post('logout', [SessionController::class, 'destroy'])->name('logout');
            Route::post('logout-everywhere', [SessionController::class, 'destroyAll'])->name('logout-everywhere');
        });
    });

    Route::middleware('auth:sanctum')->group(function () {
        Route::apiResource('messages', MessageController::class)->only(['index', 'store', 'show']);

        Route::post('messages/{message}/evaluation', [MessageEvaluationController::class, 'store'])
            ->name('messages.evaluation.store');
    });
});
