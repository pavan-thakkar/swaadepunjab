<?php

use App\Http\Controllers\Api\MenuController;
use App\Http\Controllers\Api\OrderController;
use App\Http\Controllers\Api\AuthController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
*/

// Menu routes
Route::prefix('menu')->group(function () {
    Route::get('/', [MenuController::class, 'index']);
    Route::get('/{menuItem}', [MenuController::class, 'show']);
    Route::get('/{menuItem}/reviews', [App\Http\Controllers\Api\ReviewController::class, 'index']);
    Route::post('/{menuItem}/reviews', [App\Http\Controllers\Api\ReviewController::class, 'store']);
});

// Order routes
Route::prefix('orders')->group(function () {
    Route::get('/', [OrderController::class, 'index']);
    Route::get('/history', [OrderController::class, 'history']);
    Route::post('/', [OrderController::class, 'store']);
    Route::get('/{identifier}', [OrderController::class, 'show']);
    Route::patch('/{order}/status', [OrderController::class, 'updateStatus']);
});

// Marketing cards route
Route::get('/marketing-cards', function () {
    return response()->json([
        'data' => App\Models\MarketingCard::where('is_active', true)
            ->orderBy('sort_order', 'asc')
            ->get()
    ]);
});

// Delivery Settings route
Route::get('/delivery-settings', function () {
    return response()->json([
        'data' => App\Models\DeliveryFeeSetting::orderBy('min_distance_km', 'asc')->get()
    ]);
});
Route::post('/calculate-delivery-fee', [OrderController::class, 'calculateFee']);

// Reviews user history route
Route::get('/reviews/history', [App\Http\Controllers\Api\ReviewController::class, 'userHistory']);

// Categories route
Route::get('/categories', function () {
    return response()->json([
        'data' => App\Models\Category::where('is_active', true)
            ->orderBy('sort_order', 'asc')
            ->get()
    ]);
});

// Authentication & OTP routes
Route::post('/otp/send', [AuthController::class, 'sendOtp']);
Route::post('/otp/verify', [AuthController::class, 'verifyOtp']);
Route::post('/auth/google', [AuthController::class, 'googleLogin']);


