<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\MenuItem;
use App\Models\Order;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class OrderController extends Controller
{
    /**
     * Place a new order.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'customer_name'        => 'required|string|max:255',
            'customer_email'       => 'nullable|email',
            'customer_phone'       => 'required|string|max:20',
            'order_type'           => 'nullable|string|in:delivery,dine_in,takeaway',
            'table_number'         => 'nullable|string|max:50',
            'pickup_time'          => 'nullable|string|max:100',
            'delivery_address'     => 'required_if:order_type,delivery|nullable|string',
            'city'                 => 'required_if:order_type,delivery|nullable|string|max:100',
            'special_instructions' => 'nullable|string|max:500',
            'coupon_code'          => 'nullable|string|max:40',
            'redeem_points'        => 'nullable|integer|min:0|max:1000000',
            'payment_method'       => 'in:cash_on_delivery,card,razorpay',
            'items'                => 'required|array|min:1',
            'items.*.menu_item_id' => 'required|exists:menu_items,id',
            'items.*.quantity'     => 'required|integer|min:1|max:20',
            'items.*.options'          => 'sometimes|nullable|array|max:30',
            'items.*.options.*.group'  => 'required|string|max:80',
            'items.*.options.*.choice' => 'required|string|max:80',
            'latitude'             => 'nullable|numeric',
            'longitude'            => 'nullable|numeric',
        ]);

        // Calculate totals from DB prices (never trust client prices)
        Order::expireUnpaid();
        $payOnline = ($validated['payment_method'] ?? 'cash_on_delivery') === 'razorpay';
        if ($payOnline && ! \App\Services\Razorpay::enabled()) {
            return response()->json(['message' => 'Online payment is not available right now. Please choose cash.'], 422);
        }

        // Same dish + same choices are merged; choices are checked and priced here
        [$orderItems, $subtotal] = \App\Services\OrderLines::build($validated['items']);

        $orderType   = $validated['order_type'] ?? 'delivery';
        $deliveryFee = 0;
        $distanceKm  = null;

        if ($orderType === 'delivery') {
            $deliveryStatus = \App\Models\ReceiptSetting::deliveryStatus();
            if (! $deliveryStatus['available']) {
                return response()->json(['success' => false, 'message' => $deliveryStatus['message']], 422);
            }
        }

        if ($orderType === 'delivery') {
            $address = $validated['delivery_address'] ?? '';
            $city = $validated['city'] ?? 'Gandhinagar';
            
            $calc = \App\Models\DeliveryFeeSetting::calculateDistanceAndFee($address, $city, $subtotal, $request->latitude, $request->longitude);
            
            if ($calc['error'] && str_contains($calc['error'], 'too far')) {
                return response()->json([
                    'success' => false,
                    'message' => $calc['error']
                ], 422);
            }
            
            $deliveryFee = $calc['fee'];
            $distanceKm = $calc['distance_km'];
        }
        // Admin offers: free item / discount for orders above a minimum amount
        $pricing = \App\Services\OrderPricing::calculate($subtotal, $validated['coupon_code'] ?? null, $validated['customer_phone']);
        if ($pricing['error']) {
            return response()->json(['message' => $pricing['error'], 'coupon_error' => true], 422);
        }
        $applied = ['offer' => $pricing['offer']];
        $discount = $pricing['offer_discount'];
        $couponDiscount = $pricing['coupon_discount'];
        if ($pricing['free_line']) {
            $orderItems[] = $pricing['free_line'];
        }
        // Loyalty points (only for the logged-in owner of this phone number)
        $pointsUsed = 0;
        $pointsDiscount = 0.0;
        if (! empty($validated['redeem_points'])) {
            $identity = \App\Support\CustomerToken::fromRequest($request);
            if (! \App\Support\CustomerToken::ownsPhone($identity, $validated['customer_phone'])) {
                return response()->json(['message' => 'Please log in with this phone number to use your points.', 'points_error' => true], 422);
            }
            $balance = \App\Services\Loyalty::balance($validated['customer_phone']);
            $r = \App\Services\Loyalty::redeemable((int) $validated['redeem_points'], $balance, max(0, $subtotal - $discount - $couponDiscount));
            $pointsUsed = $r['points'];
            $pointsDiscount = $r['discount'];
        }

        $foodAfterDiscounts = max(0, $subtotal - $discount - $couponDiscount - $pointsDiscount);
        $gst = \App\Services\Gst::calculate($foodAfterDiscounts);
        $total = round($foodAfterDiscounts + $gst['add'] + $deliveryFee, 2);

        $pickupTime = $validated['pickup_time'] ?? null;
        if ($pickupTime) {
            $pickupTime = trim($pickupTime);
            if (is_numeric($pickupTime)) {
                $minutes = (int)$pickupTime;
                $arrivalTime = now()->addMinutes($minutes)->format('h:i A');
                $pickupTime = "{$minutes} minutes (at {$arrivalTime})";
            } elseif (preg_match('/^(\d+)\s*(min|mins|minute|minutes)$/i', $pickupTime, $matches)) {
                $minutes = (int)$matches[1];
                $arrivalTime = now()->addMinutes($minutes)->format('h:i A');
                $pickupTime = "{$minutes} minutes (at {$matches[1]} mins)";
            }
        }

        $order = Order::create([
            'order_number'         => Order::generateOrderNumber(),
            'customer_name'        => $validated['customer_name'],
            'customer_email'       => $validated['customer_email'] ?? null,
            'customer_phone'       => $validated['customer_phone'],
            'order_type'           => $orderType,
            'table_number'         => $validated['table_number'] ?? null,
            'pickup_time'          => $pickupTime,
            'delivery_address'     => $validated['delivery_address'] ?? ($orderType === 'dine_in' ? 'Dine In' : 'Take Away'),
            'city'                 => $validated['city'] ?? 'Gandhinagar',
            'subtotal'             => $subtotal,
            'delivery_fee'         => $deliveryFee,
            'total'                => $total,
            // Online orders wait for Razorpay to confirm payment before the kitchen sees them
            'status'               => $payOnline ? 'awaiting_payment' : 'pending',
            'payment_method'       => $validated['payment_method'] ?? 'cash_on_delivery',
            'payment_status'       => $payOnline ? 'pending' : null,
            'special_instructions' => $validated['special_instructions'] ?? null,
            'estimated_delivery_at' => now()->addMinutes(45),
            'latitude'             => $request->latitude ?? null,
            'longitude'            => $request->longitude ?? null,
            'distance_km'          => $distanceKm,
            'edit_token'           => $editToken = \Illuminate\Support\Str::random(48),
            'discount'             => $discount,
            'offer_id'             => $applied['offer']?->id,
            'offer_title'          => $applied['offer']?->title,
            'coupon_code'          => $pricing['coupon']?->code,
            'coupon_discount'      => $couponDiscount,
            'points_redeemed'      => $pointsUsed,
            'points_discount'      => $pointsDiscount,
            'gst_amount'           => $gst['gst_amount'],
            'gst_rate'             => $gst['gst_rate'],
            'gst_mode'             => $gst['gst_mode'],
        ]);
        if ($pointsUsed > 0) {
            \App\Models\LoyaltyTransaction::create([
                'phone' => $order->customer_phone, 'order_id' => $order->id, 'points' => -$pointsUsed,
                'type' => 'redeem', 'note' => "Used on order {$order->order_number}",
            ]);
        }

        $order->items()->createMany($orderItems);
        $pricing['coupon']?->increment('used_count');

        $payment = null;
        if ($payOnline) {
            try {
                $payment = \App\Services\Razorpay::checkoutFor($order);
            } catch (\RuntimeException $e) {
                // Could not start payment: cancel this order (points/coupon come back) so the customer can retry
                $pricing['coupon']?->decrement('used_count');
                $order->update(['status' => 'cancelled', 'payment_status' => 'failed']);
                return response()->json(['message' => $e->getMessage(), 'payment_error' => true], 422);
            }
        }

        return response()->json([
            'message'    => $payOnline ? 'Order created. Please complete the payment.' : 'Order placed successfully!',
            'data'       => $order->load('items'),
            'edit_token' => $editToken,
            'payment'    => $payment,
        ], 201);
    }

    /** Website: is online payment switched on? (only the public Key ID, never the secret) */
    /** Rating page: order summary (needs the secret from the rating link). */
    public function ratingShow(Request $request, string $identifier): JsonResponse
    {
        $order = Order::with('items')->where('order_number', $identifier)->first();
        if (! $order || ! hash_equals($order->ratingToken(), (string) $request->query('t', ''))) {
            return response()->json(['message' => 'This rating link is not valid.'], 404);
        }
        return response()->json([
            'order_number' => $order->order_number,
            'name'         => trim(explode(' ', (string) $order->customer_name)[0] ?? ''),
            'date'         => $order->created_at?->format('d M Y, h:i A'),
            'status'       => $order->status,
            'items'        => $order->items->map(fn ($i) => ['name' => $i->name, 'quantity' => (int) $i->quantity])->values(),
            'total'        => (float) $order->total,
            'rating'       => $order->order_rating,
            'feedback'     => $order->order_feedback,
        ]);
    }

    /** Save the customer's rating (1–5 stars + optional comment). */
    public function ratingStore(Request $request, string $identifier): JsonResponse
    {
        $data = $request->validate([
            't'        => 'required|string|max:64',
            'rating'   => 'required|integer|min:1|max:5',
            'feedback' => 'nullable|string|max:1000',
        ]);
        $order = Order::where('order_number', $identifier)->first();
        if (! $order || ! hash_equals($order->ratingToken(), $data['t'])) {
            return response()->json(['message' => 'This rating link is not valid.'], 404);
        }
        if ($order->status === 'cancelled' || $order->status === 'awaiting_payment') {
            return response()->json(['message' => 'This order can\'t be rated.'], 422);
        }
        $order->forceFill([
            'order_rating'   => (int) $data['rating'],
            'order_feedback' => trim((string) ($data['feedback'] ?? '')) ?: null,
            'rated_at'       => now(),
            // A new low rating needs a fresh call
            'feedback_called_at' => (int) $data['rating'] <= 2 ? null : $order->feedback_called_at,
        ])->save();

        return response()->json(['message' => 'Thank you for your feedback!', 'rating' => $order->order_rating]);
    }

    public function paymentSettings(): JsonResponse
    {
        $on = \App\Services\Razorpay::enabled();
        return response()->json([
            'razorpay'  => $on,
            'key_id'    => $on ? \App\Services\Razorpay::keyId() : null,
            'test_mode' => $on && \App\Services\Razorpay::isTestMode(),
        ]);
    }

    /** Device that placed the order (edit token) or its logged-in owner. */
    private function canPay(Request $request, Order $order): bool
    {
        $token = (string) ($request->header('X-Order-Token') ?: $request->input('edit_token', ''));
        return ($order->edit_token && $token !== '' && hash_equals($order->edit_token, $token))
            || \App\Support\CustomerToken::ownsOrder(\App\Support\CustomerToken::fromRequest($request), $order);
    }

    /** Start / retry online payment for an order that is waiting for payment. */
    public function paymentStart(Request $request, string $identifier): JsonResponse
    {
        Order::expireUnpaid();
        $order = Order::where('order_number', $identifier)->firstOrFail();
        if (! $this->canPay($request, $order)) {
            return response()->json(['message' => 'You can only pay for orders placed from this device.'], 403);
        }
        if ($order->payment_status === 'paid') {
            return response()->json(['message' => 'This order is already paid.', 'paid' => true, 'data' => $order->fresh('items')]);
        }
        if ($order->status !== 'awaiting_payment' || $order->payment_method !== 'razorpay') {
            return response()->json(['message' => 'This order is not waiting for online payment.'], 422);
        }
        try {
            return response()->json(['payment' => \App\Services\Razorpay::checkoutFor($order)]);
        } catch (\RuntimeException $e) {
            $order->refresh();
            return response()->json(['message' => $e->getMessage(), 'paid' => $order->payment_status === 'paid'], 422);
        }
    }

    /** Razorpay Checkout finished: check the signature on the server, then mark paid. */
    public function paymentVerify(Request $request, string $identifier): JsonResponse
    {
        $data = $request->validate([
            'razorpay_order_id'   => 'required|string|max:64',
            'razorpay_payment_id' => 'required|string|max:64',
            'razorpay_signature'  => 'required|string|max:128',
        ]);
        $order = Order::where('order_number', $identifier)->firstOrFail();
        if (! $this->canPay($request, $order)) {
            return response()->json(['message' => 'You can only pay for orders placed from this device.'], 403);
        }
        if (! \App\Services\Razorpay::verifyCheckout($order, $data['razorpay_order_id'], $data['razorpay_payment_id'], $data['razorpay_signature'])) {
            return response()->json(['message' => 'We could not confirm this payment. If money was deducted, it will be confirmed automatically in a few minutes or refunded by Razorpay.'], 422);
        }
        return response()->json(['message' => 'Payment received. Your order is placed!', 'data' => $order->fresh('items')]);
    }

    /** "I've paid — check again": ask Razorpay now (no 15-second wait). */
    public function paymentCheck(Request $request, string $identifier): JsonResponse
    {
        $order = Order::where('order_number', $identifier)->firstOrFail();
        if (! $this->canPay($request, $order)) {
            return response()->json(['message' => 'You can only check orders placed from this device.'], 403);
        }
        $result = \App\Services\Razorpay::syncFromRazorpayOrder($order);
        $order->refresh();
        $messages = [
            'paid'    => 'Payment received. Your order is placed!',
            'pending' => 'Razorpay has not received this payment yet. If money was deducted, please wait a few minutes and check again.',
            'failed'  => 'The payment failed at the bank. Please try paying again, or choose cash.',
            'error'   => 'Could not reach Razorpay right now. Please try again in a minute.',
        ];
        return response()->json(['result' => $result, 'message' => $messages[$result] ?? '', 'data' => $order->load('items')]);
    }

    /** Customer gives up on online payment and pays cash instead. */
    public function payWithCash(Request $request, string $identifier): JsonResponse
    {
        $order = Order::where('order_number', $identifier)->firstOrFail();
        if (! $this->canPay($request, $order)) {
            return response()->json(['message' => 'You can only change orders placed from this device.'], 403);
        }
        \App\Services\Razorpay::syncFromRazorpayOrder($order);
        $order->refresh();
        if ($order->payment_status === 'paid') {
            return response()->json(['message' => 'This order is already paid online.', 'data' => $order->fresh('items')]);
        }
        if ($order->status !== 'awaiting_payment') {
            return response()->json(['message' => 'This order can no longer be changed.'], 422);
        }
        $order->forceFill([
            'payment_method' => 'cash_on_delivery',
            'payment_status' => null,
            'status'         => 'pending',
            'created_at'     => now(),
            'estimated_delivery_at' => now()->addMinutes(45),
        ])->save();
        return response()->json(['message' => 'Order placed. Please pay when you receive it.', 'data' => $order->fresh('items')]);
    }

    /** Razorpay → our server (Dashboard → Webhooks). */
    public function razorpayWebhook(Request $request): JsonResponse
    {
        $ok = \App\Services\Razorpay::handleWebhook($request->getContent(), $request->header('X-Razorpay-Signature'));
        return response()->json(['ok' => $ok], $ok ? 200 : 400);
    }

    /**
     * Track a specific order by ID or order_number.
     */
    /**
     * Let the customer change items / special instructions within the edit window.
     */
    public function update(Request $request, string $identifier): JsonResponse
    {
        $validated = $request->validate([
            'edit_token'           => 'required|string',
            'special_instructions' => 'nullable|string|max:500',
            'items'                => 'sometimes|array|min:1',
            'items.*.menu_item_id' => 'required_with:items|exists:menu_items,id',
            'items.*.quantity'     => 'required_with:items|integer|min:1|max:20',
            'items.*.is_free'      => 'sometimes|boolean',
            'items.*.options'          => 'sometimes|nullable|array|max:30',
            'items.*.options.*.group'  => 'required|string|max:80',
            'items.*.options.*.choice' => 'required|string|max:80',
        ]);

        $order = Order::with('items')
            ->where('order_number', $identifier)
            ->orWhere('id', $identifier)
            ->firstOrFail();

        if (! $order->edit_token || ! hash_equals($order->edit_token, $validated['edit_token'])) {
            return response()->json(['message' => 'You can only change orders placed from this device.'], 403);
        }

        // Small grace period for slow networks
        $minutes = Order::editWindowMinutes();
        $deadline = $order->created_at->copy()->addMinutes($minutes)->addSeconds(10);
        if ($minutes === 0 || $order->status !== 'pending' || now()->gt($deadline)) {
            return response()->json(['message' => 'Sorry, the time to change this order is over. Please call the restaurant.'], 422);
        }

        $itemsChanged = array_key_exists('items', $validated);

        if ($itemsChanged && $order->isPaidOnline()) {
            $current = [];
            foreach ($order->items->where('is_free', false) as $i) {
                $k = \App\Services\OrderLines::key((int) $i->menu_item_id, $i->options ?? []);
                $current[$k] = ($current[$k] ?? 0) + (int) $i->quantity;
            }
            $wanted = [];
            foreach ($validated['items'] as $i) {
                if (! empty($i['is_free'])) {
                    continue;
                }
                $k = \App\Services\OrderLines::key((int) $i['menu_item_id'], $i['options'] ?? []);
                $wanted[$k] = ($wanted[$k] ?? 0) + (int) $i['quantity'];
            }
            ksort($current); ksort($wanted);
            if ($current != $wanted) {
                return response()->json(['message' => 'This order is already paid online, so items cannot be changed. You can still update special instructions.'], 422);
            }
            $itemsChanged = false;
        }

        \Illuminate\Support\Facades\DB::transaction(function () use ($order, $validated, $itemsChanged) {
            if ($itemsChanged) {
                // Merge duplicate lines, recalculate from DB prices (dishes already in the order stay allowed)
                $paidLines = array_filter($validated['items'], fn ($i) => empty($i['is_free']));
                if (empty($paidLines)) {
                    abort(response()->json(['message' => 'Your order needs at least one item.'], 422));
                }
                [$orderItems, $subtotal] = \App\Services\OrderLines::build($paidLines, \App\Services\OrderLines::keysOf($order->items));

                $deliveryFee = 0;
                if (($order->order_type ?? 'delivery') === 'delivery') {
                    $calc = \App\Models\DeliveryFeeSetting::calculateDistanceAndFee(
                        $order->delivery_address ?? '', $order->city ?? 'Gandhinagar', $subtotal, $order->latitude, $order->longitude
                    );
                    $deliveryFee = $calc['error'] ? (float) $order->delivery_fee : $calc['fee'];
                }

                // Re-check offers + the coupon the customer used (coupon is dropped if no longer valid)
                $pricing = \App\Services\OrderPricing::calculate($subtotal, $order->coupon_code, $order->customer_phone, $order->id, false);
                $applied = ['offer' => $pricing['offer'], 'discount' => $pricing['offer_discount']];
                if ($pricing['free_line']) {
                    $orderItems[] = $pricing['free_line'];
                }
                if ($order->coupon_code && ! $pricing['coupon']) {
                    \App\Models\Coupon::findByCode($order->coupon_code)?->decrement('used_count');
                    $order->coupon_code = null;
                }
                $order->coupon_discount = $pricing['coupon_discount'];

                $order->items()->delete();
                $order->items()->createMany($orderItems);
                $order->subtotal = $subtotal;
                $order->delivery_fee = $deliveryFee;
                $order->discount = $applied['discount'];
                $order->offer_id = $applied['offer']?->id;
                $order->offer_title = $applied['offer']?->title;
                // Loyalty points: keep what still fits the new amount, give back the rest
                if ($order->points_redeemed > 0) {
                    $s = \App\Services\Loyalty::settings();
                    $eligible = max(0, $subtotal - $applied['discount'] - $pricing['coupon_discount']);
                    $maxPts = $s['point_value'] > 0 ? (int) floor(($eligible * $s['max_redeem_percent'] / 100) / $s['point_value']) : 0;
                    $keep = min((int) $order->points_redeemed, $maxPts);
                    if ($keep < $s['min_redeem']) {
                        $keep = 0;
                    }
                    $giveBack = (int) $order->points_redeemed - $keep;
                    if ($giveBack > 0) {
                        \App\Models\LoyaltyTransaction::create([
                            'phone' => $order->customer_phone, 'order_id' => $order->id, 'points' => $giveBack,
                            'type' => 'refund', 'note' => "Returned after order {$order->order_number} was changed",
                        ]);
                    }
                    $order->points_redeemed = $keep;
                    $order->points_discount = round($keep * $s['point_value'], 2);
                }
                $foodAfterDiscounts = max(0, $subtotal - $applied['discount'] - $pricing['coupon_discount'] - (float) $order->points_discount);
                // Keep the GST rate the order was placed with
                $gst = \App\Services\Gst::forExistingOrder($order, $foodAfterDiscounts);
                $order->gst_amount = $gst['gst_amount'];
                $order->total = round($foodAfterDiscounts + $gst['add'] + $deliveryFee, 2);
            }

            if (array_key_exists('special_instructions', $validated)) {
                $order->special_instructions = $validated['special_instructions'];
            }

            $order->edited_at = now();
            $order->save();
        });

        return response()->json([
            'message' => 'Your order has been updated!',
            'data'    => $order->fresh('items'),
        ]);
    }

    /**
     * Customer cancels their own order during the change window (before the restaurant accepts).
     */
    public function cancel(Request $request, string $identifier): JsonResponse
    {
        $validated = $request->validate(['edit_token' => 'required|string']);

        $order = Order::where('order_number', $identifier)->firstOrFail();

        if (! $order->edit_token || ! hash_equals($order->edit_token, $validated['edit_token'])) {
            return response()->json(['message' => 'You can only cancel orders placed from this device.'], 403);
        }

        // An online order that was never paid can always be cancelled (after a last check with Razorpay)
        if ($order->status === 'awaiting_payment') {
            \App\Services\Razorpay::syncFromRazorpayOrder($order);
            $order->refresh();
        }
        $unpaidOnline = $order->status === 'awaiting_payment' && $order->payment_status !== 'paid';

        $minutes = Order::editWindowMinutes();
        $deadline = $order->created_at->copy()->addMinutes($minutes)->addSeconds(10);
        if (! $unpaidOnline && ($minutes === 0 || $order->status !== 'pending' || now()->gt($deadline))) {
            return response()->json(['message' => 'This order can no longer be cancelled here. Please call the restaurant.'], 422);
        }
        if ($order->payment_method === 'razorpay' && $order->payment_status === 'paid') {
            return response()->json(['message' => 'This order is already paid online. Please call the restaurant to cancel it and get your refund.'], 422);
        }

        if ($order->coupon_code) {
            \App\Models\Coupon::findByCode($order->coupon_code)?->decrement('used_count');
        }
        $order->edited_at = now();
        $order->status = 'cancelled'; // loyalty points are returned automatically
        if ($unpaidOnline) {
            $order->payment_status = 'failed';
        }
        $order->save();

        return response()->json(['message' => 'Your order has been cancelled.', 'data' => $order->fresh('items')]);
    }

    /**
     * Customer types a coupon at checkout: tell them if it works and how much it saves.
     */
    public function checkCoupon(Request $request): JsonResponse
    {
        $data = $request->validate([
            'code'     => 'required|string|max:40',
            'subtotal' => 'required|numeric|min:0',
            'phone'    => 'nullable|string|max:20',
        ]);

        $pricing = \App\Services\OrderPricing::calculate((float) $data['subtotal'], $data['code'], $data['phone'] ?? null);
        if ($pricing['error']) {
            return response()->json(['valid' => false, 'message' => $pricing['error']], 422);
        }

        $c = $pricing['coupon'];
        return response()->json([
            'valid'               => true,
            'message'             => "{$c->code} applied! You save ₹" . number_format($pricing['coupon_discount'], 0),
            'code'                => $c->code,
            'description'         => $c->description,
            'discount_type'       => $c->discount_type,
            'discount_value'      => (float) $c->discount_value,
            'max_discount'        => $c->max_discount !== null ? (float) $c->max_discount : null,
            'min_order_amount'    => (float) $c->min_order_amount,
            'combine_with_offers' => $c->combine_with_offers,
            'coupon_discount'     => $pricing['coupon_discount'],
        ]);
    }

    public function show(Request $request, string $identifier): JsonResponse
    {
        $order = Order::with('items')
            ->where('order_number', $identifier)
            ->orWhere(fn ($q) => ctype_digit($identifier) ? $q->where('id', $identifier) : $q->whereRaw('1 = 0'))
            ->firstOrFail();

        // Full details only for the device that placed the order or its logged-in owner
        $orderToken = (string) $request->header('X-Order-Token', '');
        $isOwner = ($order->edit_token && $orderToken !== '' && hash_equals($order->edit_token, $orderToken))
            || \App\Support\CustomerToken::ownsOrder(\App\Support\CustomerToken::fromRequest($request), $order);

        // Customer came back from paying: ask Razorpay (at most every 15 s per order)
        if ($isOwner && $order->status === 'awaiting_payment' && $order->razorpay_order_id
            && \Illuminate\Support\Facades\Cache::add('rzp-sync-' . $order->id, 1, 15)) {
            \App\Services\Razorpay::syncFromRazorpayOrder($order);
            $order->refresh()->load('items');
        }
        Order::expireUnpaid();

        $data = $order->toArray();
        $data['is_owner'] = $isOwner;
        if ($isOwner && $order->status === 'delivered') {
            $data['rating_url_query'] = 'o=' . urlencode((string) $order->order_number) . '&t=' . $order->ratingToken();
        }
        if (! $isOwner) {
            unset($data['razorpay_order_id'], $data['razorpay_payment_id']);
        }
        if (! $isOwner) {
            $last4 = substr(preg_replace('/\D/', '', (string) $order->customer_phone), -4);
            $data['customer_phone'] = '******' . $last4;
            $data['customer_email'] = null;
            $data['customer_name'] = strtok((string) $order->customer_name, ' ') ?: 'Customer';
            $data['delivery_address'] = ($order->order_type ?? 'delivery') === 'delivery' ? 'Hidden for privacy' : $order->delivery_address;
            $data['latitude'] = null;
            $data['longitude'] = null;
        }

        return response()->json(['data' => $data]);
    }




    /**
     * Get order history by phone number or email address.
     */
    public function history(Request $request): JsonResponse
    {
        // Only the logged-in customer can see their own orders
        $identity = \App\Support\CustomerToken::fromRequest($request);
        if (! $identity) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Please log in to see your orders.',
            ], 401);
        }

        $orders = Order::with('items')
            ->where(function ($q) use ($identity) {
                if ($identity['phone']) {
                    $q->orWhere('customer_phone', 'like', '%' . $identity['phone']);
                }
                if ($identity['email']) {
                    $q->orWhere('customer_email', $identity['email']);
                }
            })
            ->latest()
            ->limit(100)
            ->get();

        return response()->json(['data' => $orders]);
    }


    /**
     * Calculate distance and fee for the frontend checkout screen.
     */
    public function calculateFee(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'delivery_address' => 'required|string',
            'city'             => 'nullable|string',
            'subtotal'         => 'nullable|numeric',
            'latitude'         => 'nullable|numeric',
            'longitude'        => 'nullable|numeric',
        ]);

        $address = $validated['delivery_address'];
        $city = $validated['city'] ?? 'Gandhinagar';
        $subtotal = (float) ($validated['subtotal'] ?? 0);
        $latitude = isset($validated['latitude']) ? (float)$validated['latitude'] : null;
        $longitude = isset($validated['longitude']) ? (float)$validated['longitude'] : null;

        $calc = \App\Models\DeliveryFeeSetting::calculateDistanceAndFee($address, $city, $subtotal, $latitude, $longitude);

        return response()->json([
            'success' => true,
            'distance_km' => $calc['distance_km'],
            'fee' => $calc['fee'],
            'error' => $calc['error']
        ]);
    }
}
