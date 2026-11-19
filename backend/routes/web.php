<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Admin\MenuImportController;

Route::get('/', function () {
    return view('welcome');
});

// Dedicated large-file menu import (bypasses Livewire memory overhead)
Route::get('/admin/menu-import', [MenuImportController::class, 'show']);
Route::post('/admin/menu-import/upload', [MenuImportController::class, 'upload']);

// Alarm polling endpoint — returns pending order count for admin-custom.js alarm
Route::get('/admin/alarm/pending-count', function () {
    if (!auth()->guard('web')->check()) {
        return response()->json(['count' => 0]);
    }
    $count = \App\Models\Order::where('status', 'pending')->count();
    return response()->json(['count' => $count]);
})->middleware(['web']);

// Print bill and/or kitchen ticket (any logged-in admin account)
Route::get('/admin/orders/{order}/print', function (\App\Models\Order $order) {
    $user = auth()->guard('web')->user();
    abort_unless($user && $user->is_active, 403);
    $type = in_array(request('type'), ['bill', 'kot', 'both'], true) ? request('type') : 'bill';
    return view('admin.print-order', ['order' => $order->load('items'), 'type' => $type]);
})->middleware(['web'])->name('admin.order.print');
