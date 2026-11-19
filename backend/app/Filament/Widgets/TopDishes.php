<?php

namespace App\Filament\Widgets;

use App\Models\OrderItem;
use Filament\Widgets\Widget;

class TopDishes extends Widget
{
    protected static string $view = 'filament.widgets.top-dishes';
    protected static ?int $sort = 5;
    protected int | string | array $columnSpan = 1;

    public static function canView(): bool
    {
        return (bool) auth()->user()?->hasRole(['owner', 'manager']);
    }

    protected function getViewData(): array
    {
        $top = OrderItem::query()
            ->selectRaw('order_items.name as name, SUM(order_items.quantity) as qty')
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->whereDate('orders.created_at', today())
            ->where('orders.status', '!=', 'cancelled')
            ->where(fn ($q) => $q->where('order_items.is_free', false)->orWhereNull('order_items.is_free'))
            ->groupBy('order_items.name')
            ->orderByDesc('qty')
            ->limit(5)
            ->get();
        return ['top' => $top];
    }
}
