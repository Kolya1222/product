<?php

use roilafx\Product\Controllers\PresetController;
use roilafx\Product\Controllers\PresetImportController;
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

Route::get('/import', [PresetImportController::class, 'form'])->name('presets.module.import');
Route::post('/import', [PresetImportController::class, 'store'])->name('presets.module.import.store');

Route::get('variant/create', [VariantController::class, 'create'])->name('variant.create');
Route::get('variant/{id}/edit', [VariantController::class, 'edit'])->name('variant.edit');
