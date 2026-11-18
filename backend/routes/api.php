<?php

use App\Http\Controllers\Api\MenuController;
use App\Http\Controllers\Api\OrderController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\PlacesController;
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
    Route::put('/{identifier}', [OrderController::class, 'update'])->middleware('throttle:20,1');
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

// "What's New" cards strip (managed in admin)
Route::get('/featured-cards', function () {
    return response()->json([
        'title'    => App\Models\SiteSetting::getValue('featured_section_title', "What's New"),
        'subtitle' => App\Models\SiteSetting::getValue('featured_section_subtitle'),
        'data'     => App\Models\FeaturedCard::where('is_active', true)
            ->orderBy('sort_order')->orderBy('id')
            ->get(['id', 'title', 'badge', 'image_path', 'menu_item_id']),
    ]);
});

// Active offers (free item / discount above a minimum order amount)
Route::get('/offers', function () {
    return response()->json([
        'data' => App\Models\Offer::live()->with('freeItem:id,name,price,image')
            ->orderBy('min_order_amount')
            ->get(['id', 'title', 'description', 'min_order_amount', 'reward_type', 'free_menu_item_id', 'free_quantity', 'discount_value', 'max_discount']),
    ]);
});

// Is home delivery available right now? (ON/OFF + hours from admin)
Route::get('/delivery-status', function () {
    return response()->json(App\Models\ReceiptSetting::deliveryStatus());
});

// Coupon check at checkout
Route::post('/coupons/check', [App\Http\Controllers\Api\OrderController::class, 'checkCoupon'])->middleware('throttle:30,1');

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
Route::post('/auth/firebase', [AuthController::class, 'firebaseLogin']);

// Google Places API proxy routes (keeps API key secure on the server)
Route::get('/places/autocomplete', [PlacesController::class, 'autocomplete']);
Route::get('/places/geocode', [PlacesController::class, 'geocode']);
Route::get('/places/reverse', [PlacesController::class, 'reverse']);




