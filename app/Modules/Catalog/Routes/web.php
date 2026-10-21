<?php

declare(strict_types=1);

use App\Modules\Catalog\Equipment\Http\CatalogController;
use Illuminate\Support\Facades\Route;

Route::middleware(['web', 'auth', 'can:manage-catalog'])->prefix('gestion/materiels')->group(function (): void {
    Route::get('/', [CatalogController::class, 'index'])->name('manageCatalog');
    Route::get('/nouveau', [CatalogController::class, 'create'])->name('catalog.create');
    Route::post('/', [CatalogController::class, 'store'])->name('catalog.store');
    Route::get('/lectures', [CatalogController::class, 'page'])->name('catalog.page');
    Route::get('/operations', [CatalogController::class, 'operation'])->name('catalog.operation');
    Route::get('/{equipment}', [CatalogController::class, 'show'])->name('catalog.show');
    Route::patch('/{equipment}', [CatalogController::class, 'update'])->name('catalog.update');
    Route::post('/{equipment}/archivage', [CatalogController::class, 'archive'])->name('catalog.archive');
    Route::post('/{equipment}/reactivation', [CatalogController::class, 'reactivate'])->name('catalog.reactivate');
    Route::get('/{equipment}/lecture', [CatalogController::class, 'detail'])->name('catalog.detail');
});
