<?php

use roilafx\Product\Controllers\PresetController;
use roilafx\Product\Controllers\ProductImportController;
use roilafx\Product\Controllers\PresetMassAssignController;
use roilafx\Product\Controllers\VariantController;
use roilafx\Product\Controllers\ProductExportController;
use Illuminate\Support\Facades\Route;

Route::get('/',                 [PresetController::class, 'index'])->name('presets.module.index');

Route::get('/create',           [PresetController::class, 'create'])->name('presets.module.create');
Route::post('/',                [PresetController::class, 'store'])->name('presets.module.store');
Route::get('/{preset}/edit',    [PresetController::class, 'edit'])->name('presets.module.edit');
Route::post('/{preset}/edit',   [PresetController::class, 'update'])->name('presets.module.update');
Route::post('/{preset}/delete', [PresetController::class, 'destroy'])->name('presets.module.destroy');

Route::prefix('mass-assign')->group(function () {
    Route::get('/',         [PresetMassAssignController::class, 'form'])->name('presets.module.massAssign');
    Route::post('/',        [PresetMassAssignController::class, 'store'])->name('presets.module.massAssign.store');
    Route::get('/children', [PresetMassAssignController::class, 'children'])->name('presets.module.massAssign.children');
});

Route::prefix('import')->group(function () {
    Route::get('/',                 [ProductImportController::class, 'index'])->name('presets.module.import');

    Route::post('/upload-chunk',    [ProductImportController::class, 'uploadChunk'])->name('presets.module.import.upload');
    Route::post('/read-chunk',      [ProductImportController::class, 'readChunk'])->name('presets.module.import.read');
    Route::post('/process-chunk',   [ProductImportController::class, 'processChunk'])->name('presets.module.import.process');
    Route::post('/finalize-upload', [ProductImportController::class, 'finalizeUpload'])->name('presets.module.import.finalize');

    Route::prefix('configs')->group(function () {
        Route::get('/create',       [ProductImportController::class, 'createConfig'])->name('presets.module.import.config.create');
        Route::post('/store',       [ProductImportController::class, 'storeConfig'])->name('presets.module.import.config.store');
        Route::get('/{id}/edit',    [ProductImportController::class, 'editConfig'])->name('presets.module.import.config.edit');
        Route::post('/{id}/update', [ProductImportController::class, 'updateConfig'])->name('presets.module.import.config.update');
    });
});

Route::prefix('export')->group(function () {
    Route::get('/',                 [ProductExportController::class, 'index'])->name('presets.module.export');
    Route::post('/start',           [ProductExportController::class, 'start'])->name('presets.module.export.start');
    Route::post('/process-chunk',   [ProductExportController::class, 'processChunk'])->name('presets.module.export.process');
    Route::get('/download',         [ProductExportController::class, 'download'])->name('presets.module.export.download');
});

Route::prefix('variant')->group(function () {
    Route::get('/create',       [VariantController::class, 'create'])->name('variant.create');
    Route::get('/{id}/edit',    [VariantController::class, 'edit'])->name('variant.edit');
});