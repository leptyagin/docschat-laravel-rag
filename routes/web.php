<?php

declare(strict_types=1);

use App\Http\Controllers\Admin\ConversationController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\DocumentController;
use App\Http\Controllers\Admin\LoginController;
use App\Http\Controllers\Admin\SettingsController;
use App\Http\Controllers\Admin\UnansweredController;
use App\Http\Controllers\LandingController;
use App\Http\Controllers\Widget\DocumentController as WidgetDocumentController;
use App\Http\Controllers\Widget\WidgetController;
use App\Http\Middleware\BlockReadOnlyUsers;
use Illuminate\Support\Facades\Route;

Route::get('/', LandingController::class)->name('home');

Route::prefix('widget/{bot:public_key}')->name('widget.')->group(function (): void {
    Route::get('/', [WidgetController::class, 'show'])->name('show');
    Route::get('documents/{document}', [WidgetDocumentController::class, 'show'])->scopeBindings()->name('documents.show');
});

Route::prefix('admin')->name('admin.')->group(function (): void {
    Route::middleware('guest')->group(function (): void {
        Route::get('login', [LoginController::class, 'create'])->name('login');
        Route::post('login', [LoginController::class, 'store'])->middleware('throttle:login');
    });

    Route::middleware('auth')->group(function (): void {
        Route::post('logout', [LoginController::class, 'destroy'])->name('logout');

        Route::get('/', DashboardController::class)->name('dashboard');
        Route::get('documents', [DocumentController::class, 'index'])->name('documents.index');
        Route::get('conversations', [ConversationController::class, 'index'])->name('conversations.index');
        Route::get('conversations/{managedConversation}', [ConversationController::class, 'show'])->name('conversations.show');
        Route::get('unanswered', UnansweredController::class)->name('unanswered');
        Route::get('settings', [SettingsController::class, 'edit'])->name('settings.edit');

        Route::middleware(BlockReadOnlyUsers::class)->group(function (): void {
            Route::post('documents', [DocumentController::class, 'store'])->name('documents.store');
            Route::post('documents/url', [DocumentController::class, 'storeUrl'])->name('documents.store-url');
            Route::post('documents/{managedDocument}/reindex', [DocumentController::class, 'reindex'])->name('documents.reindex');
            Route::delete('documents/{managedDocument}', [DocumentController::class, 'destroy'])->name('documents.destroy');
            Route::put('settings', [SettingsController::class, 'update'])->name('settings.update');
        });
    });
});
