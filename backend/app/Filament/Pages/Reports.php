<?php

namespace App\Filament\Pages;

use App\Models\Order;
use App\Models\OrderItem;
use Filament\Pages\Page;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class Reports extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-chart-bar';
    protected static ?string $navigationLabel = 'Reports & Analytics';
    protected static ?string $navigationGroup = 'Operations';
    protected static ?int $navigationSort = 3;

    protected static string $view = 'filament.pages.reports';

    public ?string $startDate = null;
    public ?string $endDate = null;

    public function mount(): void
    {
        $this->startDate = request()->query('startDate', Carbon::now()->startOfMonth()->format('Y-m-d'));
        $this->endDate = request()->query('endDate', Carbon::now()->format('Y-m-d'));
    }

    public function getViewData(): array
    {
        $start = Carbon::parse($this->startDate)->startOfDay();
        $end = Carbon::parse($this->endDate)->endOfDay();

        // 1. Core KPIs (within range)
        $totalRevenue = Order::whereBetween('created_at', [$start, $end])
            ->where('status', '!=', 'cancelled')
            ->sum('total');
        $totalOrders = Order::whereBetween('created_at', [$start, $end])->count();
        $deliveredOrders = Order::whereBetween('created_at', [$start, $end])
            ->where('status', 'delivered')
            ->count();
        $averageOrderValue = Order::whereBetween('created_at', [$start, $end])
            ->where('status', '!=', 'cancelled')
            ->avg('total') ?? 0;

        // Today's stats
        $todayRevenue = Order::whereDate('created_at', Carbon::today())
            ->where('status', '!=', 'cancelled')
            ->sum('total');
        $todayOrders = Order::whereDate('created_at', Carbon::today())->count();

        // 2. Order Status Breakdown (within range)
        $statusBreakdown = Order::select('status', DB::raw('count(*) as count'), DB::raw('sum(total) as revenue'))
            ->whereBetween('created_at', [$start, $end])
            ->groupBy('status')
            ->get()
            ->keyBy('status')
            ->toArray();

        // 3. Top Selling Items (within range)
        $topItems = OrderItem::select('order_items.name', DB::raw('SUM(order_items.quantity) as total_qty'), DB::raw('SUM(order_items.subtotal) as total_revenue'))
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->whereBetween('orders.created_at', [$start, $end])
            ->groupBy('order_items.name')
            ->orderByDesc('total_qty')
            ->limit(5)
            ->get();

        // 4. Sales by Payment Method (within range)
        $paymentBreakdown = Order::select('payment_method', DB::raw('count(*) as count'), DB::raw('sum(total) as revenue'))
            ->whereBetween('created_at', [$start, $end])
            ->where('status', '!=', 'cancelled')
            ->groupBy('payment_method')
            ->get();

        // 5. Daily Sales Trend (within range)
        $dailyTrend = Order::select(
                DB::raw("date(created_at) as date"), 
                DB::raw("count(*) as order_count"), 
                DB::raw("sum(total) as daily_revenue")
            )
            ->whereBetween('created_at', [$start, $end])
            ->where('status', '!=', 'cancelled')
            ->groupBy(DB::raw("date(created_at)"))
            ->orderBy('date', 'asc')
            ->get();

        // 6. Detailed Bills List (within range)
        $bills = Order::whereBetween('created_at', [$start, $end])
            ->with('items')
            ->orderBy('created_at', 'desc')
            ->get();

        return [
            'startDate'         => $this->startDate,
            'endDate'           => $this->endDate,
            'totalRevenue'      => $totalRevenue,
            'totalOrders'       => $totalOrders,
            'deliveredOrders'   => $deliveredOrders,
            'averageOrderValue' => $averageOrderValue,
            'todayRevenue'      => $todayRevenue,
            'todayOrders'       => $todayOrders,
            'statusBreakdown'   => $statusBreakdown,
            'topItems'          => $topItems,
            'paymentBreakdown'  => $paymentBreakdown,
            'dailyTrend'        => $dailyTrend,
            'bills'             => $bills,
        ];
    }
}
