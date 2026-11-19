<?php

namespace App\Filament\Widgets;

use App\Models\Order;
use Filament\Widgets\Widget;

class SalesByHour extends Widget
{
    protected static string $view = 'filament.widgets.sales-by-hour';
    protected static ?int $sort = 4;
    protected int | string | array $columnSpan = 1;

    public static function canView(): bool
    {
        return (bool) auth()->user()?->hasRole(['owner', 'manager']);
    }

    protected function getViewData(): array
    {
        $rows = Order::whereDate('created_at', today())->where('status', '!=', 'cancelled')->get(['created_at', 'total']);
        $byHour = array_fill(11, 13, 0.0); // 11 AM .. 11 PM
        foreach ($rows as $r) {
            $h = (int) $r->created_at->timezone(config('app.timezone'))->format('G');
            if ($h < 11) $h = 11;
            if ($h > 23) $h = 23;
            $byHour[$h] += (float) $r->total;
        }
        $max = max($byHour) ?: 1;
        $peak = array_search(max($byHour), $byHour);
        $bars = [];
        foreach ($byHour as $h => $v) {
            $label = date('g A', mktime($h, 0));
            $bars[] = ['pct' => max(2, round($v / $max * 100)), 'peak' => $v > 0 && $h === $peak, 'title' => "{$label}: ₹" . number_format($v, 0)];
        }
        return ['bars' => $bars, 'total' => array_sum($byHour), 'peakLabel' => max($byHour) > 0 ? date('g A', mktime($peak, 0)) : null];
    }
}
