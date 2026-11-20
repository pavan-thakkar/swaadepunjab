<?php

namespace App\Filament\Resources\OnlinePaymentResource\Pages;

use App\Filament\Resources\OnlinePaymentResource;
use App\Models\Order;
use Filament\Resources\Components\Tab;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Database\Eloquent\Builder;

class ListOnlinePayments extends ListRecords
{
    protected static string $resource = OnlinePaymentResource::class;

    private function base(): Builder
    {
        return Order::where('payment_method', 'razorpay');
    }

    public function getSubheading(): ?string
    {
        $today = OnlinePaymentResource::scopeState($this->base()->whereDate('created_at', today()), 'success');
        $amount = (float) (clone $today)->sum('total');
        $count = (clone $today)->count();
        $failed = OnlinePaymentResource::scopeState($this->base()->whereDate('created_at', today()), 'failed')->count();

        return 'Today: ₹' . number_format($amount, 2) . " received from {$count} payment" . ($count === 1 ? '' : 's')
            . " · {$failed} failed. Money reaches your bank in Razorpay's next settlement.";
    }

    public function getTabs(): array
    {
        $count = fn (string $s) => OnlinePaymentResource::scopeState($this->base(), $s)->count();

        return [
            'all' => Tab::make('All')
                ->badge($this->base()->count()),
            'success' => Tab::make('✅ Success')
                ->badge($count('success'))->badgeColor('success')
                ->modifyQueryUsing(fn (Builder $q) => OnlinePaymentResource::scopeState($q, 'success')),
            'failed' => Tab::make('❌ Failed')
                ->badge($count('failed'))->badgeColor('danger')
                ->modifyQueryUsing(fn (Builder $q) => OnlinePaymentResource::scopeState($q, 'failed')),
            'waiting' => Tab::make('⏳ Waiting')
                ->badge($count('waiting'))->badgeColor('warning')
                ->modifyQueryUsing(fn (Builder $q) => OnlinePaymentResource::scopeState($q, 'waiting')),
            'refunded' => Tab::make('↩️ Refunded')
                ->badge($count('refunded'))
                ->modifyQueryUsing(fn (Builder $q) => OnlinePaymentResource::scopeState($q, 'refunded')),
        ];
    }
}
