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
            'latitude'             => 'nullable|numeric',
            'longitude'            => 'nullable|numeric',
        ]);

        // Calculate totals from DB prices (never trust client prices)
        $orderItems = [];
        $subtotal   = 0;

        foreach ($validated['items'] as $item) {
            $menuItem = MenuItem::findOrFail($item['menu_item_id']);
            if (! $menuItem->is_available) {
                return response()->json([
                    'message' => "{$menuItem->name} is currently unavailable.",
                ], 422);
            }
            $itemSubtotal = $menuItem->price * $item['quantity'];
            $subtotal    += $itemSubtotal;

            $orderItems[] = [
                'menu_item_id' => $menuItem->id,
                'name'         => $menuItem->name,
                'price'        => $menuItem->price,
                'quantity'     => $item['quantity'],
                'subtotal'     => $itemSubtotal,
            ];
        }

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

        $total = max(0, $subtotal - $discount - $couponDiscount - $pointsDiscount) + $deliveryFee;

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
            'status'               => 'pending',
            'payment_method'       => $validated['payment_method'] ?? 'cash_on_delivery',
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
        ]);
        if ($pointsUsed > 0) {
            \App\Models\LoyaltyTransaction::create([
                'phone' => $order->customer_phone, 'order_id' => $order->id, 'points' => -$pointsUsed,
                'type' => 'redeem', 'note' => "Used on order {$order->order_number}",
            ]);
        }

        $order->items()->createMany($orderItems);
        $pricing['coupon']?->increment('used_count');

        return response()->json([
            'message'    => 'Order placed successfully!',
            'data'       => $order->load('items'),
            'edit_token' => $editToken,
        ], 201);
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
            $current = $order->items->where('is_free', false)->mapWithKeys(fn ($i) => [$i->menu_item_id => (int) $i->quantity])->sort()->all();
            $wanted = [];
            foreach ($validated['items'] as $i) {
                if (! empty($i['is_free'])) {
                    continue;
                }
                $wanted[$i['menu_item_id']] = ($wanted[$i['menu_item_id']] ?? 0) + (int) $i['quantity'];
            }
            ksort($current); ksort($wanted);
            if ($current != $wanted) {
                return response()->json(['message' => 'This order is already paid online, so items cannot be changed. You can still update special instructions.'], 422);
            }
            $itemsChanged = false;
        }

        \Illuminate\Support\Facades\DB::transaction(function () use ($order, $validated, $itemsChanged) {
            if ($itemsChanged) {
                // Merge duplicate lines, recalculate from DB prices
                $merged = [];
                foreach ($validated['items'] as $i) {
                    if (! empty($i['is_free'])) {
                        continue;
                    }
                    $merged[$i['menu_item_id']] = ($merged[$i['menu_item_id']] ?? 0) + (int) $i['quantity'];
                }

                if (empty($merged)) {
                    abort(response()->json(['message' => 'Your order needs at least one item.'], 422));
                }

                $orderItems = [];
                $subtotal = 0;
                foreach ($merged as $menuItemId => $qty) {
                    $menuItem = MenuItem::findOrFail($menuItemId);
                    $alreadyInOrder = $order->items->contains('menu_item_id', $menuItemId);
                    if (! $menuItem->is_available && ! $alreadyInOrder) {
                        abort(response()->json(['message' => "{$menuItem->name} is currently unavailable."], 422));
                    }
                    $qty = min($qty, 20);
                    $line = $menuItem->price * $qty;
                    $subtotal += $line;
                    $orderItems[] = [
                        'menu_item_id' => $menuItem->id,
                        'name'         => $menuItem->name,
                        'price'        => $menuItem->price,
                        'quantity'     => $qty,
                        'subtotal'     => $line,
                    ];
                }

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
                $order->total = max(0, $subtotal - $applied['discount'] - $pricing['coupon_discount'] - (float) $order->points_discount) + $deliveryFee;
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

        $minutes = Order::editWindowMinutes();
        $deadline = $order->created_at->copy()->addMinutes($minutes)->addSeconds(10);
        if ($minutes === 0 || $order->status !== 'pending' || now()->gt($deadline)) {
            return response()->json(['message' => 'This order can no longer be cancelled here. Please call the restaurant.'], 422);
        }

        if ($order->coupon_code) {
            \App\Models\Coupon::findByCode($order->coupon_code)?->decrement('used_count');
        }
        $order->edited_at = now();
        $order->status = 'cancelled'; // loyalty points are returned automatically
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

        $data = $order->toArray();
        $data['is_owner'] = $isOwner;
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
