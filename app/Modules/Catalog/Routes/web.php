<?php

declare(strict_types=1);

use App\Infrastructure\Http\Session\ReadOnlySession;
use App\Modules\Catalog\Equipment\Http\CatalogController;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\Facades\Route;

Route::middleware(['web', 'auth', 'can:manage-catalog'])->prefix('gestion/materiels')->group(function (): void {
    Route::get('/', [CatalogController::class, 'index'])->name('manageCatalog');
    Route::get('/nouveau', [CatalogController::class, 'create'])->name('catalog.create');
    Route::post('/', [CatalogController::class, 'store'])->name('catalog.store');
    Route::post('/operations/cloture', [CatalogController::class, 'closeOperation'])->middleware('throttle:catalog.operation.close')->name('catalog.operation.close');
    Route::middleware(ReadOnlySession::class)->withoutMiddleware([StartSession::class, AddQueuedCookiesToResponse::class])->group(function (): void {
        Route::get('/lectures', [CatalogController::class, 'page'])->name('catalog.page');
        Route::get('/operations', [CatalogController::class, 'operation'])->name('catalog.operation');
        Route::get('/{equipment}/lecture', [CatalogController::class, 'detail'])->name('catalog.detail');
    });
    Route::get('/{equipment}', [CatalogController::class, 'show'])->name('catalog.show');
    Route::patch('/{equipment}', [CatalogController::class, 'update'])->name('catalog.update');
    Route::post('/{equipment}/archivage', [CatalogController::class, 'archive'])->name('catalog.archive');
    Route::post('/{equipment}/reactivation', [CatalogController::class, 'reactivate'])->name('catalog.reactivate');
});
