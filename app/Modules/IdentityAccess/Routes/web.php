<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Laravel\Fortify\Http\Controllers\AuthenticatedSessionController;

Route::middleware('web')->group(function (): void {
    Route::get('/login', [AuthenticatedSessionController::class, 'create'])->middleware('guest:web')->name('login');
    Route::post('/login', [AuthenticatedSessionController::class, 'store'])->middleware(['guest:web', 'throttle:login'])->name('login.store');
    Route::post('/logout', [AuthenticatedSessionController::class, 'destroy'])->middleware('auth:web')->name('logout');
});
