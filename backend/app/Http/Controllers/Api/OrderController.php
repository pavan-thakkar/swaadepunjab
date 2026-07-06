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
            $address = $validated['delivery_address'] ?? '';
            $city = $validated['city'] ?? 'Amritsar';
            
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
        $total       = $subtotal + $deliveryFee;

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
            'city'                 => $validated['city'] ?? 'Amritsar',
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
        ]);

        $order->items()->createMany($orderItems);

        return response()->json([
            'message' => 'Order placed successfully!',
            'data'    => $order->load('items'),
        ], 201);
    }

    /**
     * Track a specific order by ID or order_number.
     */
    public function show(string $identifier): JsonResponse
    {
        $order = Order::with('items')
            ->where('id', $identifier)
            ->orWhere('order_number', $identifier)
            ->firstOrFail();

        return response()->json(['data' => $order]);
    }

    /**
     * List all orders (for admin use via API if needed).
     */
    public function index(Request $request): JsonResponse
    {
        $query = Order::with('items')->latest();

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        return response()->json(['data' => $query->paginate(20)]);
    }

    /**
     * Update order status (used by admin).
     */
    public function updateStatus(Request $request, Order $order): JsonResponse
    {
        $validated = $request->validate([
            'status' => 'required|in:pending,accepted,preparing,out_for_delivery,delivered,cancelled',
        ]);

        $order->update(['status' => $validated['status']]);

        return response()->json([
            'message' => 'Order status updated.',
            'data'    => $order->fresh('items'),
        ]);
    }

    /**
     * Get order history by phone number.
     */
    public function history(Request $request): JsonResponse
    {
        $request->validate([
            'phone' => 'required|string|max:30',
        ]);

        $orders = Order::with('items')
            ->where('customer_phone', $request->phone)
            ->latest()
            ->get();

        return response()->json([
            'data' => $orders,
        ]);
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
        $city = $validated['city'] ?? 'Amritsar';
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
