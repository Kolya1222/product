<?php

use roilafx\Product\Controllers\VariantController;
use roilafx\Product\Controllers\AttributeController;
use roilafx\Product\Controllers\CategoryController;
use roilafx\Product\Controllers\CatalogFilterController;
use Illuminate\Support\Facades\Route;

Route::middleware([
    \EvolutionCMS\Middleware\CheckManagerAuth::class,
    \EvolutionCMS\Middleware\VerifyCsrfToken::class,
])->prefix('admin/product-variants')->group(function () {
    Route::get('/',              [VariantController::class, 'index']);
    Route::post('/',             [VariantController::class, 'store']);
    Route::get('/create',        [VariantController::class, 'create']);
    Route::get('/{id}/edit',     [VariantController::class, 'edit']);
    Route::put('/{id}',          [VariantController::class, 'update']);
    Route::delete('/{id}',       [VariantController::class, 'destroy']);
    Route::post('/save-as-preset', [VariantController::class, 'saveAsPreset']);

    Route::prefix('attributes')->group(function () {
        Route::get('/',          [AttributeController::class, 'index']);
        Route::post('/assign',   [AttributeController::class, 'assign']);
        Route::post('/',         [AttributeController::class, 'store']);
        Route::get('/types',     [AttributeController::class, 'types']);
        Route::get('/general-form', [AttributeController::class, 'generalForm'])->name('attr.generalForm');
        Route::post('/general-save', [AttributeController::class, 'saveGeneralValues'])->name('attr.generalSave');
        Route::post('/general-assign', [AttributeController::class, 'assignGeneralAttributes'])->name('attr.generalAssign');
        Route::get('/{id}',      [AttributeController::class, 'show']);
        Route::put('/{id}',      [AttributeController::class, 'update']);
        Route::delete('/{id}',   [AttributeController::class, 'destroy']);
    });

    Route::prefix('categories')->group(function () {
        Route::get('/',          [CategoryController::class, 'index']);
        Route::post('/',         [CategoryController::class, 'store']);
        Route::put('/{id}',      [CategoryController::class, 'update']);
        Route::delete('/{id}',   [CategoryController::class, 'destroy']);
    });
});

Route::get('/catalog/{catalogId}/filter', [CatalogFilterController::class, 'filter'])->name('catalog.filter');
Route::get('/catalog/{catalogId}/filter-state', [CatalogFilterController::class, 'filterState']);
