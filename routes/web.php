<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;

Route::inertia('/', 'Welcome')->name('home');

Route::middleware(['auth'])->group(function () {
    Route::inertia('dashboard', 'Dashboard')->name('dashboard');
    Route::inertia('catalogue', 'Dashboard', ['section' => 'catalog'])->name('catalogue');
    Route::inertia('mes-demandes', 'Dashboard', ['section' => 'ownRequests'])->middleware('can:borrow')->name('ownRequests');
    Route::inertia('demandes', 'Dashboard', ['section' => 'teamRequests'])->middleware('can:operate-loans')->name('teamRequests');
    Route::inertia('gestion/materiels', 'Dashboard', ['section' => 'management'])->middleware('can:manage-catalog')->name('manageCatalog');
});
