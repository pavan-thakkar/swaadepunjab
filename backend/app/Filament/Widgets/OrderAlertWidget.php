<?php

namespace App\Filament\Widgets;

use App\Models\Order;
use Filament\Widgets\Widget;

class OrderAlertWidget extends Widget
{
    protected static string $view = 'filament.widgets.order-alert-widget';

    protected static ?int $sort = -10;

    protected int | string | array $columnSpan = 'full';

    public ?int $lastOrderId = null;

    // Holds pending orders as array of data to render inline cards
    public array $pendingOrderCards = [];

    public function mount(): void
    {
        $latestOrder = Order::latest('id')->first();
        $this->lastOrderId = $latestOrder ? $latestOrder->id : 0;

        // Load any existing pending orders to show on mount
        $this->loadPendingOrders();
    }

    protected function loadPendingOrders(): void
    {
        $pending = Order::with('items')
            ->where('status', 'pending')
            ->orderBy('id', 'desc')
            ->take(5)
            ->get();

        $this->pendingOrderCards = $pending->map(function (Order $order) {
            return $this->buildOrderCard($order);
        })->values()->toArray();
    }

    protected function buildOrderCard(Order $order): array
    {
        $itemsSummary = $order->items->map(function ($item) {
            return [
                'name'     => $item->name,
                'quantity' => $item->quantity,
                'price'    => number_format((float)$item->price, 2),
                'subtotal' => number_format((float)$item->subtotal, 2),
            ];
        })->toArray();

        return [
            'id'            => $order->id,
            'order_number'  => $order->order_number,
            'customer_name' => $order->customer_name,
            'customer_phone'=> $order->customer_phone,
            'total'         => number_format((float)$order->total, 2),
            'items'         => $itemsSummary,
            'created_at'    => $order->created_at->format('h:i A'),
            'special_instructions' => $order->special_instructions,
            'order_type'    => $order->order_type,
            'pickup_time'   => $order->pickup_time,
        ];
    }

    public function acceptOrderWithPrepTime(int $orderId, int $prepMinutes): void
    {
        $order = Order::find($orderId);
        if ($order && $order->status === 'pending') {
            $order->advance($prepMinutes); // starts the kitchen countdown
        }
        // Remove from pending cards
        $this->pendingOrderCards = array_values(
            array_filter($this->pendingOrderCards, fn ($c) => $c['id'] !== $orderId)
        );
    }

    public function rejectOrder(int $orderId, string $reason = 'busy'): void
    {
        $order = Order::find($orderId);
        if ($order && $order->status === 'pending') {
            $order->reject(array_key_exists($reason, Order::REJECT_REASONS) ? $reason : 'other');
            \Filament\Notifications\Notification::make()->title("{$order->order_number} rejected — " . (Order::REJECT_REASONS[$reason] ?? 'Other reason'))
                ->body($order->isPaidViaRazorpay() ? 'Paid online: refund it from the order page.' : null)->warning()->send();
        }
        $this->pendingOrderCards = array_values(
            array_filter($this->pendingOrderCards, fn ($c) => $c['id'] !== $orderId)
        );
    }

    public function checkForNewOrders(): void
    {
        Order::autoRejectStale();
        // Drop cards for orders already accepted / rejected elsewhere (kitchen board, another screen, auto-reject)
        if ($this->pendingOrderCards) {
            $stillNew = Order::whereIn('id', array_column($this->pendingOrderCards, 'id'))->where('status', 'pending')->pluck('id')->all();
            $this->pendingOrderCards = array_values(array_filter($this->pendingOrderCards, fn ($c) => in_array($c['id'], $stillNew, true)));
        }
        $latestOrder = Order::latest('id')->first();

        if ($latestOrder && $latestOrder->id > $this->lastOrderId) {
            $newOrders = Order::with('items')
                ->where('id', '>', $this->lastOrderId)
                ->orderBy('id', 'asc')
                ->get();

            $this->lastOrderId = $latestOrder->id;

            foreach ($newOrders as $order) {
                if ($order->status === 'pending') {
                    $card = $this->buildOrderCard($order);
                    // Add to front of the list — Alpine $watch detects the count increase & fires alarm
                    array_unshift($this->pendingOrderCards, $card);
                }
            }
        }
    }
}
