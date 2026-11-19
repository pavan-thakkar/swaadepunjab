<?php

namespace App\Filament\Pages;

use Filament\Pages\Dashboard as BaseDashboard;

class Dashboard extends BaseDashboard
{
    protected static ?string $navigationIcon = 'heroicon-o-home';
    protected static ?int $navigationSort = -100;

    public function getHeading(): string
    {
        $h = (int) now()->format('G');
        $greet = $h < 12 ? 'Good morning' : ($h < 17 ? 'Good afternoon' : 'Good evening');
        $name = strtok((string) auth()->user()?->name, ' ') ?: '';
        return trim("{$greet}, {$name}", ', ');
    }

    public function getSubheading(): ?string
    {
        return now()->format('l, j M') . ' · ' . (auth()->user()?->roleLabel() ?? '');
    }

    public function getColumns(): int | string | array
    {
        return ['default' => 1, 'lg' => 3];
    }

    public function getWidgets(): array
    {
        return [
            \App\Filament\Widgets\OrderAlertWidget::class,
            \App\Filament\Widgets\StatsOverview::class,
            \App\Filament\Widgets\LiveOrdersBoard::class,
            \App\Filament\Widgets\QuickSwitches::class,
            \App\Filament\Widgets\SalesByHour::class,
            \App\Filament\Widgets\TopDishes::class,
            \App\Filament\Widgets\RecentActivity::class,
        ];
    }
}
