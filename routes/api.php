<?php

use App\Http\Controllers\Api\V1\MessageController;
use App\Http\Controllers\Api\V1\MessageEvaluationController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->name('api.v1.')->middleware('auth:sanctum')->group(function () {
    Route::apiResource('messages', MessageController::class)->only(['index', 'store', 'show']);

    Route::post('messages/{message}/evaluation', [MessageEvaluationController::class, 'store'])
        ->name('messages.evaluation.store');
});
