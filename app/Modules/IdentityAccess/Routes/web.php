<?php

declare(strict_types=1);

use App\Infrastructure\Http\Session\ReadOnlySession;
use App\Modules\IdentityAccess\Access\UseCases\ReadSessionContext\Http\ReadSessionContextController;
use Illuminate\Broadcasting\BroadcastController;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\Facades\Route;
use Laravel\Fortify\Http\Controllers\AuthenticatedSessionController;

Route::middleware('web')->group(function (): void {
    Route::middleware(ReadOnlySession::class)->withoutMiddleware([StartSession::class, AddQueuedCookiesToResponse::class])->group(function (): void {
        Route::get('/access/context', ReadSessionContextController::class)->name('access.context');
        Route::post('/broadcasting/auth', [BroadcastController::class, 'authenticate'])->middleware('auth')->name('broadcasting.auth');
    });
    Route::get('/login', [AuthenticatedSessionController::class, 'create'])->middleware('guest:web')->name('login');
    Route::post('/login', [AuthenticatedSessionController::class, 'store'])->middleware(['guest:web', 'throttle:login'])->name('login.store');
    Route::post('/logout', [AuthenticatedSessionController::class, 'destroy'])->middleware('auth:web')->name('logout');
});
