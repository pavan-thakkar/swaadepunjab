<?php

namespace App\Filament\Widgets;

use App\Models\Order;
use App\Models\MenuItem;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Carbon\Carbon;

class StatsOverview extends BaseWidget
{
    protected static ?int $sort = 1;

    protected function getStats(): array
    {
        $todayRevenue = Order::whereDate('created_at', Carbon::today())
            ->where('status', '!=', 'cancelled')
            ->sum('total');
        
        $todayOrders = Order::whereDate('created_at', Carbon::today())->count();
        $totalOrders = Order::count();
        $totalItems = MenuItem::count();

        return [
            Stat::make("Today's Sales", '₹' . number_format($todayRevenue, 0))
                ->description("From {$todayOrders} orders today")
                ->descriptionIcon('heroicon-m-arrow-trending-up')
                ->color('success'),
            
            Stat::make('Total Orders', $totalOrders)
                ->description('All-time order count')
                ->descriptionIcon('heroicon-m-shopping-bag')
                ->color('info'),
            
            Stat::make('Menu Catalog', $totalItems)
                ->description('Active Punjabi dishes')
                ->descriptionIcon('heroicon-m-cake')
                ->color('warning'),
        ];
    }
}
