<?php

use roilafx\Product\Controllers\PresetController;
use roilafx\Product\Controllers\ProductImportController;
use roilafx\Product\Controllers\PresetMassAssignController;
use roilafx\Product\Controllers\VariantController;
use Illuminate\Support\Facades\Route;

Route::get('/', [PresetController::class, 'index'])->name('presets.module.index');
Route::get('/create', [PresetController::class, 'create'])->name('presets.module.create');
Route::post('/', [PresetController::class, 'store'])->name('presets.module.store');
Route::get('/{preset}/edit', [PresetController::class, 'edit'])->name('presets.module.edit');
Route::post('/{preset}/edit', [PresetController::class, 'update'])->name('presets.module.update');
Route::post('/{preset}/delete', [PresetController::class, 'destroy'])->name('presets.module.destroy');

Route::get('/mass-assign', [PresetMassAssignController::class, 'form'])->name('presets.module.massAssign');
Route::post('/mass-assign', [PresetMassAssignController::class, 'store'])->name('presets.module.massAssign.store');
Route::get('/mass-assign/children', [PresetMassAssignController::class, 'children'])->name('presets.module.massAssign.children');

Route::get('/import', [ProductImportController::class, 'index'])->name('presets.module.import');
Route::get('/import/configs/create', [ProductImportController::class, 'createConfig'])->name('presets.module.import.config.create');
Route::post('/import/configs/store', [ProductImportController::class, 'storeConfig'])->name('presets.module.import.config.store');
Route::get('/import/configs/{id}/edit', [ProductImportController::class, 'editConfig'])->name('presets.module.import.config.edit');
Route::post('/import/configs/{id}/update', [ProductImportController::class, 'updateConfig'])->name('presets.module.import.config.update');

Route::post('/import/upload-chunk', [ProductImportController::class, 'uploadChunk'])->name('presets.module.import.upload');
Route::post('/import/finalize-upload', [ProductImportController::class, 'finalizeUpload'])->name('presets.module.import.finalize');
Route::post('/import/read-chunk', [ProductImportController::class, 'readChunk'])->name('presets.module.import.read');
Route::post('/import/process-chunk', [ProductImportController::class, 'processChunk'])->name('presets.module.import.process');

Route::get('variant/create', [VariantController::class, 'create'])->name('variant.create');
Route::get('variant/{id}/edit', [VariantController::class, 'edit'])->name('variant.edit');