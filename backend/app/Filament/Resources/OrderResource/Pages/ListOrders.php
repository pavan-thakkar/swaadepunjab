<?php

namespace App\Filament\Resources\OrderResource\Pages;

use App\Filament\Resources\OrderResource;
use App\Filament\Widgets\OrderAlertWidget;
use Filament\Actions;
use Filament\Resources\Components\Tab;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Database\Eloquent\Builder;

class ListOrders extends ListRecords
{
    protected static string $resource = OrderResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }

    protected function getHeaderWidgets(): array
    {
        return [
            OrderAlertWidget::class,
        ];
    }

    public function getTabs(): array
    {
        return [
            'all' => Tab::make('All Orders')
                ->icon('heroicon-o-list-bullet')
                ->badge(fn () => \App\Models\Order::count()),

            'pending' => Tab::make('Pending')
                ->icon('heroicon-o-clock')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', 'pending'))
                ->badge(fn () => \App\Models\Order::where('status', 'pending')->count())
                ->badgeColor('warning'),

            'accepted' => Tab::make('Accepted')
                ->icon('heroicon-o-check-circle')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', 'accepted'))
                ->badge(fn () => \App\Models\Order::where('status', 'accepted')->count())
                ->badgeColor('info'),

            'preparing' => Tab::make('Preparing')
                ->icon('heroicon-o-fire')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', 'preparing'))
                ->badge(fn () => \App\Models\Order::where('status', 'preparing')->count())
                ->badgeColor('primary'),

            'dispatched' => Tab::make('Dispatched')
                ->icon('heroicon-o-truck')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', 'out_for_delivery'))
                ->badge(fn () => \App\Models\Order::where('status', 'out_for_delivery')->count())
                ->badgeColor('info'),

            'delivered' => Tab::make('Delivered')
                ->icon('heroicon-o-gift')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', 'delivered'))
                ->badge(fn () => \App\Models\Order::where('status', 'delivered')->count())
                ->badgeColor('success'),

            'cancelled' => Tab::make('Cancelled')
                ->icon('heroicon-o-x-circle')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', 'cancelled'))
                ->badge(fn () => \App\Models\Order::where('status', 'cancelled')->count())
                ->badgeColor('danger'),
        ];
    }
}
