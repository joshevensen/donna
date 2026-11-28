<?php

use App\Http\Controllers\Api\V1\Auth\LoginCodeController;
use App\Http\Controllers\Api\V1\Auth\SessionController;
use App\Http\Controllers\Api\V1\FileBodyController;
use App\Http\Controllers\Api\V1\FileContentController;
use App\Http\Controllers\Api\V1\FileController;
use App\Http\Controllers\Api\V1\MeController;
use App\Http\Controllers\Api\V1\MessageController;
use App\Http\Controllers\Api\V1\MessageEvaluationController;
use App\Http\Controllers\Api\V1\MessageRetryController;
use App\Http\Controllers\Api\V1\PresenceController;
use App\Http\Controllers\Api\V1\PushSubscriptionController;
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
        Route::get('me', [MeController::class, 'show'])->name('me.show');

        // Sends and retries start a model call, so they are the ones that are limited.
        Route::apiResource('messages', MessageController::class)
            ->only(['index', 'store', 'show'])
            ->middlewareFor('store', 'throttle:messages');

        Route::post('messages/{message}/retry', [MessageRetryController::class, 'store'])
            ->middleware('throttle:messages')
            ->name('messages.retry.store');

        Route::post('messages/{message}/evaluation', [MessageEvaluationController::class, 'store'])
            ->name('messages.evaluation.store');

        // Files. Uploads are limited; there is no delete: that is Donna's to do, when asked.
        Route::apiResource('files', FileController::class)
            ->only(['index', 'store', 'show'])
            ->middlewareFor('store', 'throttle:files');

        Route::patch('files/{file}', [FileController::class, 'update'])->name('files.update');
        Route::put('files/{file}', [FileBodyController::class, 'update'])->name('files.body.update');
        Route::get('files/{file}/content', [FileContentController::class, 'show'])->name('files.content.show');

        // Web Push for the device making the request, and whether the app is open on it.
        Route::post('push-subscriptions', [PushSubscriptionController::class, 'store'])->name('push-subscriptions.store');
        Route::delete('push-subscriptions', [PushSubscriptionController::class, 'destroy'])->name('push-subscriptions.destroy');
        Route::delete('presence', [PresenceController::class, 'destroy'])->name('presence.destroy');
    });
});
