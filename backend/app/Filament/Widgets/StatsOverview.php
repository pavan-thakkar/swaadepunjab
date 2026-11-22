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
        $today = Order::whereDate('created_at', today())->whereNotIn('status', ['cancelled', 'awaiting_payment']);
        $lastWeek = Order::whereDate('created_at', today()->subWeek())->whereNotIn('status', ['cancelled', 'awaiting_payment'])
            ->whereTime('created_at', '<=', now()->format('H:i:s'));

        $sales = (float) (clone $today)->sum('total');
        $salesLastWeek = (float) $lastWeek->sum('total');
        $orders = (clone $today)->count();
        $pending = Order::where('status', 'pending')->count();
        $avg = $orders ? $sales / $orders : 0;
        // Money by how it was paid: settled mode first; otherwise paid online (Razorpay) / UPI / card / cash (COD / pay later)
        $by = ['online' => [0, 0.0], 'cash' => [0, 0.0], 'upi' => [0, 0.0], 'card' => [0, 0.0]];
        $toCollect = [0, 0.0];
        foreach ((clone $today)->get(['id', 'total', 'payment_method', 'payment_status', 'settle_mode', 'settle_cash', 'settle_upi', 'settle_card', 'status']) as $o) {
            $t = (float) $o->total;
            if ($o->settle_mode === 'split') {
                foreach (['cash', 'upi', 'card'] as $k) {
                    $v = (float) $o->{'settle_' . $k};
                    if ($v > 0) { $by[$k][0]++; $by[$k][1] += $v; }
                }
                continue;
            }
            $mode = $o->settle_mode
                ?: ($o->payment_method === 'razorpay' ? 'online' : match ($o->payment_method) { 'upi_counter' => 'upi', 'card' => 'card', default => 'cash' });
            if (! isset($by[$mode])) $mode = 'cash';
            $by[$mode][0]++; $by[$mode][1] += $t;
            if (! $o->settle_mode && $mode === 'cash') { $toCollect[0]++; $toCollect[1] += $t; }
        }
        $onlinePct = $sales > 0 ? round($by['online'][1] / $sales * 100) : 0;
        $n = fn (int $c) => $c . ' order' . ($c === 1 ? '' : 's');
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
                ->description("Online {$onlinePct}% of today's sales")
                ->color('gray'),
            Stat::make('Discounts given', '₹' . number_format($discounts, 0))
                ->description('Coupons ₹' . number_format($couponDisc, 0) . ' · Offers & points ₹' . number_format($discounts - $couponDisc, 0))
                ->color('gray'),

            // ---- Row 2: how today's money came in ----
            Stat::make('🌐 Online orders', '₹' . number_format($by['online'][1], 0))
                ->description($n($by['online'][0]) . ' paid on the website')
                ->color('info'),
            Stat::make('💵 Cash orders', '₹' . number_format($by['cash'][1], 0))
                ->description($n($by['cash'][0]) . ($toCollect[0] ? ' · ₹' . number_format($toCollect[1], 0) . ' not collected yet' : ' · all collected'))
                ->color($toCollect[0] ? 'warning' : 'success'),
            Stat::make('📱 UPI orders', '₹' . number_format($by['upi'][1], 0))
                ->description($n($by['upi'][0]) . ' at the counter')
                ->color('gray'),
            Stat::make('💳 Card orders', '₹' . number_format($by['card'][1], 0))
                ->description($n($by['card'][0]))
                ->color('gray'),
        ];
    }
}
