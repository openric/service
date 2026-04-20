<?php

declare(strict_types=1);

use AhgRicModel\Http\Controllers\AttributeController;
use AhgRicModel\Http\Controllers\EntityController;
use AhgRicModel\Http\Controllers\ReferenceIndexController;
use AhgRicModel\Http\Controllers\RelationAttributeController;
use AhgRicModel\Http\Controllers\RelationController;
use AhgRicModel\Http\Middleware\EnsureVersionExists;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| RiC-CM reference browser — public routes
|--------------------------------------------------------------------------
| Two route families:
|
|   A.  Unversioned URLs — 302 redirect to the latest-version URL. These are
|       what most navigation links use; the redirect keeps them aligned with
|       whichever RiC-CM version is currently considered "latest".
|
|   B.  Versioned URLs — serve directly. Use these for stable citation
|       (papers, long-lived external links). `{version}` is validated by the
|       EnsureVersionExists middleware so unknown versions 404 cleanly.
|
| Both families live under `/reference/ric-cm/…` to reserve space for other
| reference models (CIDOC-CRM, ISO 15489, PREMIS) later.
*/

// Family A — unversioned → 302 to the latest version.
Route::middleware('web')->prefix('reference/ric-cm')->name('reference.ric-cm.')->group(function () {
    Route::get('/',                                [ReferenceIndexController::class, 'redirectToLatest'])->name('index.redirect');
    Route::get('/entities',                        [EntityController::class,         'redirectToLatest'])->name('entities.redirect');
    Route::get('/entities/{id}',                   [EntityController::class,         'redirectToLatest'])->name('entities.show.redirect');
    Route::get('/attributes',                      [AttributeController::class,      'redirectToLatest'])->name('attributes.redirect');
    Route::get('/attributes/{id}',                 [AttributeController::class,      'redirectToLatest'])->name('attributes.show.redirect');
    Route::get('/relations',                       [RelationController::class,       'redirectToLatest'])->name('relations.redirect');
    Route::get('/relations/{id}',                  [RelationController::class,       'redirectToLatest'])->name('relations.show.redirect');
    Route::get('/relation-attributes',             [RelationAttributeController::class, 'redirectToLatest'])->name('relation-attributes.redirect');
    Route::get('/relation-attributes/{id}',        [RelationAttributeController::class, 'redirectToLatest'])->name('relation-attributes.show.redirect');
});

// Family B — versioned, served directly. Version validated before any controller runs.
Route::middleware(['web', EnsureVersionExists::class])
    ->prefix('reference/ric-cm/{version}')
    ->name('reference.ric-cm.')
    ->where(['version' => '[0-9]+(?:\.[0-9]+)*'])
    ->group(function () {
        Route::get('/',                            [ReferenceIndexController::class, 'index'])->name('index');
        Route::get('/entities',                    [EntityController::class,         'index'])->name('entities.index');
        Route::get('/entities/{id}',               [EntityController::class,         'show'])->name('entities.show');
        Route::get('/attributes',                  [AttributeController::class,      'index'])->name('attributes.index');
        Route::get('/attributes/{id}',             [AttributeController::class,      'show'])->name('attributes.show');
        Route::get('/relations',                   [RelationController::class,       'index'])->name('relations.index');
        Route::get('/relations/{id}',              [RelationController::class,       'show'])->name('relations.show');
        Route::get('/relation-attributes',         [RelationAttributeController::class, 'index'])->name('relation-attributes.index');
        Route::get('/relation-attributes/{id}',    [RelationAttributeController::class, 'show'])->name('relation-attributes.show');
    });
