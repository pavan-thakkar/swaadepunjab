<?php

namespace App\Filament\Resources\ActivityLogResource\Pages;

use App\Filament\Resources\ActivityLogResource;
use App\Models\ActivityLog;
use Filament\Resources\Components\Tab;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Database\Eloquent\Builder;

class ListActivityLogs extends ListRecords
{
    protected static string $resource = ActivityLogResource::class;

    public function getSubheading(): ?string
    {
        // Today's activity per staff member (website customers not counted)
        $today = ActivityLog::whereDate('created_at', today())
            ->whereNotNull('user_id')
            ->where('action', '!=', 'login')
            ->selectRaw('user_name, count(*) as n')
            ->groupBy('user_name')
            ->orderByDesc('n')
            ->limit(6)
            ->get();

        $who = $today->isEmpty()
            ? 'No staff changes yet today.'
            : 'Today: ' . $today->map(fn ($r) => "{$r->user_name} {$r->n} change" . ($r->n == 1 ? '' : 's'))->implode(' · ') . '.';

        return $who . ' Every login and change in the admin, with who did it and when. Click a row to see exactly what changed.';
    }

    public static function cancelled(Builder $q): Builder
    {
        return $q->where('action', 'status')->where('description', 'like', '%→ cancelled%');
    }

    public static function money(Builder $q): Builder
    {
        return $q->where('action', 'updated')->where(function ($w) {
            foreach (['"price"', '"total"', '"discount"', '"subtotal"', '"delivery_fee"', '"discount_value"', '"gst_rate"', '"gst_enabled"'] as $k) {
                $w->orWhere('changes', 'like', '%' . $k . '%');
            }
        });
    }

    public function getTabs(): array
    {
        $todayCount = fn (callable $scope) => $scope(ActivityLog::query()->whereDate('created_at', today()))->count();

        return [
            'all' => Tab::make('All'),
            'orders' => Tab::make('Order changes')
                ->icon('heroicon-o-shopping-cart')
                ->modifyQueryUsing(fn (Builder $q) => $q->where('subject_type', 'Order')->whereNotNull('user_id')),
            'cancelled' => Tab::make('Cancelled orders')
                ->icon('heroicon-o-x-circle')
                ->badge(fn () => $todayCount(fn ($q) => static::cancelled($q)) ?: null)->badgeColor('danger')
                ->modifyQueryUsing(fn (Builder $q) => static::cancelled($q)),
            'money' => Tab::make('Price & bill changes')
                ->icon('heroicon-o-currency-rupee')
                ->badge(fn () => $todayCount(fn ($q) => static::money($q)) ?: null)->badgeColor('warning')
                ->modifyQueryUsing(fn (Builder $q) => static::money($q)),
            'deleted' => Tab::make('Deleted')
                ->icon('heroicon-o-trash')
                ->modifyQueryUsing(fn (Builder $q) => $q->where('action', 'deleted')),
            'logins' => Tab::make('Logins')
                ->icon('heroicon-o-key')
                ->modifyQueryUsing(fn (Builder $q) => $q->whereIn('action', ['login', 'login_failed'])),
        ];
    }
}
