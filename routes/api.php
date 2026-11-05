<?php

declare(strict_types=1);

use App\Http\Controllers\Api\V1\MessageController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->name('api.v1.')->group(function (): void {
    Route::post('bots/{bot:public_key}/messages', [MessageController::class, 'store'])
        ->middleware('throttle:chat')
        ->name('messages.store');
});
