<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\OrderResource;
use App\Models\Order;
use Filament\Notifications\Notification;
use Filament\Widgets\Widget;

/** Kanban-style board of today's open orders with one-tap status buttons (all roles). */
class LiveOrdersBoard extends Widget
{
    protected static string $view = 'filament.widgets.live-orders-board';
    protected static ?int $sort = 2;
    protected int | string | array $columnSpan = ['default' => 'full', 'lg' => 2];

    /** Allowed next step for each status. */
    private const NEXT = [
        'pending'          => 'accepted',
        'accepted'         => 'preparing',
        'preparing'        => 'out_for_delivery', // delivery orders; pickup/dine-in go straight to delivered
        'out_for_delivery' => 'delivered',
    ];

    public function advance(int $orderId): void
    {
        $order = Order::find($orderId);
        if (! $order || ! isset(self::NEXT[$order->status])) {
            return;
        }
        $next = self::NEXT[$order->status];
        if ($order->status === 'preparing' && ($order->order_type ?? 'delivery') !== 'delivery') {
            $next = 'delivered';
        }
        $data = ['status' => $next];
        if ($next === 'accepted') {
            $prep = (int) ($order->prep_time_minutes ?: 20);
            $data['prep_time_minutes'] = $prep;
            $data['estimated_delivery_at'] = now()->addMinutes($prep + (($order->order_type ?? 'delivery') === 'delivery' ? 15 : 0));
        }
        $order->update($data);
        Notification::make()->title("Order {$order->order_number}: " . str_replace('_', ' ', $next))->success()->send();
    }

    protected function getViewData(): array
    {
        $orders = Order::with('items')
            ->whereIn('status', array_keys(self::NEXT))
            ->where('created_at', '>=', now()->subDay())
            ->orderBy('created_at')
            ->get();

        $card = function (Order $o) {
            $type = $o->order_type ?: 'delivery';
            $label = match ($o->status) {
                'pending' => 'Accept',
                'accepted' => 'Mark ready',
                'preparing' => $type === 'delivery' ? 'Out for delivery' : ($type === 'dine_in' ? 'Served' : 'Picked up'),
                'out_for_delivery' => 'Delivered',
                default => '',
            };
            $btn = match ($o->status) {
                'pending' => 'sp-btn-amber', 'accepted' => 'sp-btn-dark', default => 'sp-btn-green',
            };
            $items = $o->items->map(fn ($i) => ($i->quantity > 1 ? $i->quantity . '× ' : '') . preg_replace('/ \(FREE.*\)$/u', ' (free)', $i->name))->take(3)->implode(', ');
            if ($o->items->count() > 3) {
                $items .= ' +' . ($o->items->count() - 3) . ' more';
            }
            return [
                'id' => $o->id,
                'no' => substr($o->order_number, -6),
                'url' => OrderResource::getUrl('view', ['record' => $o]),
                'ago' => $o->created_at->diffForHumans(null, true, true),
                'late' => $o->status === 'pending' && $o->created_at->lt(now()->subMinutes(3)),
                'items' => $items,
                'type' => $type,
                'typeLabel' => ['delivery' => 'Delivery', 'takeaway' => 'Take Away', 'dine_in' => 'Dine In'][$type] ?? $type,
                'total' => '₹' . number_format((float) $o->total, 0),
                'action' => $label,
                'btn' => $btn,
                'note' => $o->special_instructions,
            ];
        };

        $cols = [
            ['key' => 'pending', 'title' => 'NEW', 'color' => '#B91C1C'],
            ['key' => 'accepted', 'title' => 'COOKING', 'color' => '#8A5A00'],
            ['key' => 'preparing', 'title' => 'READY', 'color' => '#11622F'],
            ['key' => 'out_for_delivery', 'title' => 'OUT FOR DELIVERY', 'color' => '#1D3F91'],
        ];
        foreach ($cols as &$c) {
            $c['cards'] = $orders->where('status', $c['key'])->values()->map($card)->all();
        }

        return ['columns' => $cols];
    }
}
