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
// Online payments (Razorpay)
Route::get('/payment-settings', [App\Http\Controllers\Api\OrderController::class, 'paymentSettings']);
Route::post('/orders/{identifier}/payment', [App\Http\Controllers\Api\OrderController::class, 'paymentStart'])->middleware('throttle:20,1');
Route::post('/orders/{identifier}/payment/verify', [App\Http\Controllers\Api\OrderController::class, 'paymentVerify'])->middleware('throttle:20,1');
Route::post('/orders/{identifier}/payment/check', [App\Http\Controllers\Api\OrderController::class, 'paymentCheck'])->middleware('throttle:20,1');
Route::get('/orders/{identifier}/rating', [App\Http\Controllers\Api\OrderController::class, 'ratingShow'])->middleware('throttle:30,1');
Route::post('/orders/{identifier}/rating', [App\Http\Controllers\Api\OrderController::class, 'ratingStore'])->middleware('throttle:10,1');
Route::post('/orders/{identifier}/pay-with-cash', [App\Http\Controllers\Api\OrderController::class, 'payWithCash'])->middleware('throttle:10,1');
Route::post('/razorpay/webhook', [App\Http\Controllers\Api\OrderController::class, 'razorpayWebhook'])->middleware('throttle:120,1');

// Monthly (corporate) meal plans
Route::get('/monthly-plans', [App\Http\Controllers\Api\MonthlyPlanController::class, 'index']);
Route::get('/monthly-plans/mine', [App\Http\Controllers\Api\MonthlyPlanController::class, 'mine'])->middleware('throttle:60,1');
Route::post('/monthly-plans/my-requests', [App\Http\Controllers\Api\MonthlyPlanController::class, 'byTokens'])->middleware('throttle:30,1');
Route::post('/monthly-plans/enquiry', [App\Http\Controllers\Api\MonthlyPlanController::class, 'enquire'])->middleware('throttle:5,1');

Route::prefix('menu')->group(function () {
    Route::get('/', [MenuController::class, 'index']);
    Route::get('/slug/{slug}', [MenuController::class, 'showBySlug'])->where('slug', '[A-Za-z0-9\-]+');
    Route::get('/{menuItem}', [MenuController::class, 'show'])->whereNumber('menuItem');
    Route::get('/{menuItem}/pairs', [MenuController::class, 'pairs'])->middleware('throttle:120,1');
    Route::get('/{menuItem}/reviews', [App\Http\Controllers\Api\ReviewController::class, 'index']);
    Route::post('/{menuItem}/reviews', [App\Http\Controllers\Api\ReviewController::class, 'store']);
});

// Order routes
Route::prefix('orders')->group(function () {
    // (Public "list all orders" route removed: it exposed every customer's details.)
    Route::get('/history', [OrderController::class, 'history'])->middleware('throttle:30,1');
    Route::post('/', [OrderController::class, 'store'])->middleware('throttle:10,1');
    Route::get('/{identifier}', [OrderController::class, 'show']);
    Route::put('/{identifier}', [OrderController::class, 'update'])->middleware('throttle:20,1');
    Route::post('/{identifier}/cancel', [OrderController::class, 'cancel'])->middleware('throttle:10,1');
    // (Public "change status" route removed: status is changed only from the admin panel.)
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
            ->with('menuItem:id,name,price,quantity_label,option_groups,customisation_ids,category,description,prep_time,rating,is_available,is_featured,image,time_limited,available_from,available_until,outside_hours')
            ->orderBy('sort_order')->orderBy('id')
            ->get(['id', 'title', 'badge', 'image_path', 'menu_item_id']),
    ]);
});

// Timed sections (e.g. Breakfast 4–7 PM) with their dishes; the website shows each one only during its hours
Route::get('/menu-sections', function () {
    $sections = App\Models\MenuSection::activeList();
    $ids = collect($sections)->flatMap(fn ($s) => $s->dishIds())->unique()->values()->all();
    $dishes = $ids ? App\Models\MenuItem::whereIn('id', $ids)->where('is_available', true)->get()->keyBy('id') : collect();
    return response()->json([
        'data' => collect($sections)->map(fn ($s) => [
            'id'       => $s->id,
            'title'    => $s->title,
            'subtitle' => $s->subtitle,
            'emoji'    => $s->emoji,
            'from'     => $s->available_from,
            'until'    => $s->available_until,
            'label'    => $s->label(),
            'now'      => $s->isOpen(),
            'items'    => collect($s->dishIds())->map(fn ($id) => $dishes->get($id))->filter()->values(),
        ])->filter(fn ($s) => count($s['items']) > 0)->values(),
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

// Loyalty points
Route::get('/loyalty/settings', function () {
    $s = App\Services\Loyalty::settings();
    return response()->json($s);
});
Route::get('/loyalty', function (Illuminate\Http\Request $request) {
    $s = App\Services\Loyalty::settings();
    $identity = App\Support\CustomerToken::fromRequest($request);
    if (! $identity) {
        return response()->json(['message' => 'Please log in to see your points.'] + ['settings' => $s], 401);
    }
    $phone = App\Support\CustomerToken::phoneFor($identity);
    return response()->json([
        'settings' => $s,
        'phone'    => $phone ? '******' . substr($phone, -4) : null,
        'balance'  => App\Services\Loyalty::balance($phone),
        'history'  => $phone
            ? App\Models\LoyaltyTransaction::where('phone', $phone)->latest()->limit(20)->get(['points', 'type', 'note', 'created_at'])
            : [],
    ]);
})->middleware('throttle:30,1');

// Public store info (name + phone for Call / WhatsApp buttons)
Route::get('/store-info', function () {
    $s = App\Models\ReceiptSetting::getSettings();
    $digits = preg_replace('/\D/', '', (string) $s->outlet_phone);
    if (strlen($digits) === 10) {
        $digits = '91' . $digits;
    }
    return response()->json([
        'name'     => $s->outlet_name,
        'phone'    => $s->outlet_phone,
        'whatsapp' => $digits ?: null,
    ]);
});

// GST settings (website cart shows the GST line the same way the bill will)
Route::get('/gst-settings', fn () => response()->json(App\Services\Gst::publicArray()));

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
            ->orderBy('sort_order', 'asc')->orderBy('name') // same order as the admin list
            ->get()
    ]);
});

// Authentication & OTP routes
Route::post('/otp/send', [AuthController::class, 'sendOtp'])->middleware('throttle:5,1');
Route::post('/otp/verify', [AuthController::class, 'verifyOtp'])->middleware('throttle:10,1');
Route::post('/auth/google', [AuthController::class, 'googleLogin'])->middleware('throttle:10,1');
Route::post('/auth/firebase', [AuthController::class, 'firebaseLogin'])->middleware('throttle:10,1');

// Google Places API proxy routes (keeps API key secure on the server)
Route::get('/places/autocomplete', [PlacesController::class, 'autocomplete']);
Route::get('/places/geocode', [PlacesController::class, 'geocode']);
Route::get('/places/reverse', [PlacesController::class, 'reverse']);




