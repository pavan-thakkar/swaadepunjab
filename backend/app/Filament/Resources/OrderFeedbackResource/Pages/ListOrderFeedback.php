<?php

namespace App\Filament\Resources\OrderFeedbackResource\Pages;

use App\Filament\Resources\OrderFeedbackResource;
use App\Models\Order;
use Filament\Resources\Components\Tab;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Database\Eloquent\Builder;

class ListOrderFeedback extends ListRecords
{
    protected static string $resource = OrderFeedbackResource::class;

    public function getSubheading(): ?string
    {
        $rated = Order::whereNotNull('order_rating');
        $count = (clone $rated)->count();
        if (! $count) {
            return 'Customers rate their order from a link sent after delivery.';
        }
        $avg = round((float) (clone $rated)->avg('order_rating'), 1);
        $month = Order::whereNotNull('order_rating')->where('rated_at', '>=', now()->startOfMonth());
        $mAvg = (clone $month)->count() ? round((float) (clone $month)->avg('order_rating'), 1) : null;

        return "Average {$avg} ★ from {$count} ratings" . ($mAvg !== null ? " · this month {$mAvg} ★" : '') . '. Ratings of 1–2 stars need a call.';
    }

    public function getTabs(): array
    {
        return [
            'call' => Tab::make('📞 Needs a call')
                ->badge(fn () => Order::badRatingsToCall() ?: null)->badgeColor('danger')
                ->modifyQueryUsing(fn (Builder $q) => $q->where('order_rating', '<=', 2)->whereNull('feedback_called_at')),
            'all' => Tab::make('All ratings'),
            'happy' => Tab::make('😊 Happy (4–5 ★)')
                ->modifyQueryUsing(fn (Builder $q) => $q->where('order_rating', '>=', 4)),
            'low' => Tab::make('😞 Low (1–2 ★)')
                ->modifyQueryUsing(fn (Builder $q) => $q->where('order_rating', '<=', 2)),
        ];
    }

    public function getDefaultActiveTab(): string | int | null
    {
        return Order::badRatingsToCall() > 0 ? 'call' : 'all';
    }
}
