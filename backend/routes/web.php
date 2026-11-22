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
    if (!auth()->guard('web')->check() || auth()->guard('web')->user()->hasRole('rider')) {
        return response()->json(['count' => 0]); // riders don't get the kitchen alarm
    }
    $count = \App\Models\Order::where('status', 'pending')->count();
    $latest = $count ? \App\Models\Order::where('status', 'pending')->latest('created_at')->first(['id', 'order_number', 'customer_name', 'total', 'order_type']) : null;
    return response()->json([
        'count'  => $count,
        'latest' => $latest ? [
            'id'    => $latest->id,
            'name'  => $latest->customer_name,
            'total' => (float) $latest->total,
            'type'  => ['delivery' => 'Delivery', 'takeaway' => 'Take Away', 'dine_in' => 'Dine In'][$latest->order_type ?? 'delivery'] ?? 'Order',
        ] : null,
    ]);
})->middleware(['web']);

// Print bill and/or kitchen ticket (any logged-in admin account)
Route::get('/admin/orders/{order}/print', function (\App\Models\Order $order) {
    $user = auth()->guard('web')->user();
    abort_unless($user && $user->is_active, 403);
    $type = in_array(request('type'), ['bill', 'kot', 'both'], true) ? request('type') : 'bill';
    return view('admin.print-order', ['order' => $order->load('items'), 'type' => $type]);
})->middleware(['web'])->name('admin.order.print');

// Printable hisab (day settlement) slip — fits an 80 mm bill printer or A4
Route::get('/admin/hisab/print', function () {
    $user = auth()->guard('web')->user();
    abort_unless($user && $user->is_active, 403);
    $day = \Carbon\Carbon::parse(request('date') ?: \App\Filament\Pages\DaySettlement::businessToday()->toDateString());
    return view('admin.hisab-print', \App\Filament\Pages\DaySettlement::summary($day) + [
        'day' => $day, 'closing' => \App\Models\DayClosing::whereDate('business_date', $day->toDateString())->first(),
        'outlet' => \App\Models\ReceiptSetting::first(),
    ]);
})->middleware(['web'])->name('admin.hisab.print');

// Printable list of monthly-plan meals for one day
Route::get('/admin/plan-deliveries/print', function () {
    $user = auth()->guard('web')->user();
    abort_unless($user && $user->is_active, 403);
    $day = \Carbon\Carbon::parse(request('date') ?: today()->toDateString());
    $plans = \App\Filament\Pages\PlanDeliveries::plansFor($day);
    $marks = \App\Models\PlanDelivery::whereDate('date', $day)->get()->keyBy('plan_enquiry_id');
    return view('admin.plan-deliveries-print', ['day' => $day, 'plans' => $plans, 'marks' => $marks, 'outlet' => \App\Models\ReceiptSetting::first()]);
})->middleware(['web'])->name('admin.plan-deliveries.print');
