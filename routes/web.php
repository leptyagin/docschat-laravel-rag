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
use App\Http\Controllers\Workspace\DocumentController as WorkspaceDocumentController;
use App\Http\Middleware\BlockReadOnlyUsers;
use App\Http\Middleware\ContentSecurityPolicy;
use App\Http\Middleware\DenyFraming;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\Facades\Route;
use Illuminate\View\Middleware\ShareErrorsFromSession;

/*
| These pages are for anonymous visitors and have no forms. Without a session,
| a flood of requests leaves nothing behind in Redis.
*/
Route::withoutMiddleware([StartSession::class, ShareErrorsFromSession::class, PreventRequestForgery::class])->group(function (): void {
    Route::get('/', LandingController::class)
        ->middleware(['throttle:pages', ContentSecurityPolicy::class])
        ->name('home');

    Route::prefix('widget/{bot:public_key}')->name('widget.')->group(function (): void {
        Route::get('/', [WidgetController::class, 'show'])
            ->middleware('throttle:pages')
            ->withoutMiddleware(DenyFraming::class)
            ->name('show');
        Route::get('documents/{document}', [WidgetDocumentController::class, 'show'])
            ->middleware('throttle:documents')
            ->scopeBindings()
            ->name('documents.show');
    });

    Route::get('read/{bot:public_key}/{document}', [WorkspaceDocumentController::class, 'show'])
        ->middleware(['throttle:documents', ContentSecurityPolicy::class])
        ->where('document', '[0-9]{1,18}')
        ->scopeBindings()
        ->name('reader.show');
});

Route::prefix('admin')->name('admin.')->middleware(['throttle:pages', ContentSecurityPolicy::class])->group(function (): void {
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
