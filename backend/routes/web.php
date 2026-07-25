<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Admin\MenuImportController;

Route::get('/', function () {
    return view('welcome');
});

// Dedicated large-file menu import (bypasses Livewire memory overhead)
Route::get('/admin/menu-import', [MenuImportController::class, 'show']);
Route::post('/admin/menu-import/upload', [MenuImportController::class, 'upload']);


