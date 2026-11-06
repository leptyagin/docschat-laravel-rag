<?php

declare(strict_types=1);

use App\Http\Controllers\Api\V1\FeedbackController;
use App\Http\Controllers\Api\V1\MessageController;
use App\Http\Middleware\LimitConcurrentAnswers;
use Illuminate\Support\Facades\Route;

Route::prefix('v1/bots/{bot:public_key}')->name('api.v1.')->group(function (): void {
    Route::post('messages', [MessageController::class, 'store'])
        ->middleware(['throttle:chat', LimitConcurrentAnswers::class])
        ->name('messages.store');

    Route::post('messages/{message}/feedback', [FeedbackController::class, 'store'])
        ->middleware('throttle:feedback')
        ->name('feedback.store');
});
