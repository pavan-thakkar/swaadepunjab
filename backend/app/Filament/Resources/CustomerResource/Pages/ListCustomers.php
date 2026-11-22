<?php

namespace App\Filament\Resources\CustomerResource\Pages;

use App\Filament\Resources\CustomerResource;
use App\Models\Order;
use Filament\Resources\Components\Tab;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Database\Eloquent\Builder;

class ListCustomers extends ListRecords
{
    protected static string $resource = CustomerResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }

    public function getSubheading(): ?string
    {
        $base = fn () => Order::query()->whereNotNull('customer_phone')->whereNotIn('customer_phone', ['', 'Counter']);
        $total = $base()->distinct()->count('customer_phone');
        $newThisMonth = $base()->select('customer_phone')->groupBy('customer_phone')
            ->havingRaw('MIN(created_at) >= ?', [now()->startOfMonth()])->get()->count();

        return "{$total} customers · {$newThisMonth} new this month. Use \"Not ordered for 30+ days\" to find customers to win back, then select them and send a WhatsApp offer.";
    }

    public function getTabs(): array
    {
        $real = "SUM(CASE WHEN status NOT IN ('cancelled','awaiting_payment') THEN 1 ELSE 0 END)";

        return [
            'all' => Tab::make('All'),
            'regulars' => Tab::make('⭐ Regulars (5+ orders)')
                ->modifyQueryUsing(fn (Builder $query) => $query->havingRaw("{$real} >= 5")),
            'new' => Tab::make('🆕 New (1 order)')
                ->modifyQueryUsing(fn (Builder $query) => $query->havingRaw("{$real} = 1")),
            'win_back' => Tab::make('💤 Not ordered in 30 days')
                ->modifyQueryUsing(fn (Builder $query) => $query->havingRaw('MAX(created_at) < ?', [now()->subDays(30)])),
        ];
    }
}
