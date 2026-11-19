<?php

namespace App\Filament\Widgets;

use App\Models\Order;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class StatsOverview extends BaseWidget
{
    protected static ?int $sort = 1;
    protected int | string | array $columnSpan = 'full';
    protected static ?string $pollingInterval = '60s';

    /** Sales figures: Owner and Manager only (hidden from counter staff). */
    public static function canView(): bool
    {
        return (bool) auth()->user()?->hasRole(['owner', 'manager']);
    }

    protected function getColumns(): int
    {
        return 4;
    }

    protected function getStats(): array
    {
        $today = Order::whereDate('created_at', today())->where('status', '!=', 'cancelled');
        $lastWeek = Order::whereDate('created_at', today()->subWeek())->where('status', '!=', 'cancelled')
            ->whereTime('created_at', '<=', now()->format('H:i:s'));

        $sales = (float) (clone $today)->sum('total');
        $salesLastWeek = (float) $lastWeek->sum('total');
        $orders = (clone $today)->count();
        $pending = Order::where('status', 'pending')->count();
        $avg = $orders ? $sales / $orders : 0;
        $online = (clone $today)->where('payment_method', '!=', 'cash_on_delivery')->count();
        $onlinePct = $orders ? round($online / $orders * 100) : 0;
        $discounts = (float) (clone $today)->sum('discount') + (float) (clone $today)->sum('coupon_discount') + (float) (clone $today)->sum('points_discount');
        $couponDisc = (float) (clone $today)->sum('coupon_discount');

        if ($salesLastWeek > 0) {
            $chg = round(($sales - $salesLastWeek) / $salesLastWeek * 100);
            $salesNote = ($chg >= 0 ? '+' : '') . $chg . '% vs last ' . now()->format('l') . ' (same time)';
        } else {
            $salesNote = 'No sales last ' . now()->format('l') . ' to compare';
        }

        return [
            Stat::make("Today's sales", '₹' . number_format($sales, 0))
                ->description($salesNote)
                ->color(isset($chg) && $chg < 0 ? 'danger' : 'success'),
            Stat::make('Orders today', (string) $orders)
                ->description($pending > 0 ? "{$pending} waiting to be accepted" : 'No orders waiting')
                ->color($pending > 0 ? 'danger' : 'gray'),
            Stat::make('Average order', '₹' . number_format($avg, 0))
                ->description("Online {$onlinePct}% · Cash " . (100 - $onlinePct) . '%')
                ->color('gray'),
            Stat::make('Discounts given', '₹' . number_format($discounts, 0))
                ->description('Coupons ₹' . number_format($couponDisc, 0) . ' · Offers & points ₹' . number_format($discounts - $couponDisc, 0))
                ->color('gray'),
        ];
    }
}
