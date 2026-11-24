<?php

namespace App\Filament\Pages;

use App\Filament\Resources\OrderResource;
use App\Models\Order;
use Filament\Notifications\Notification;
use Filament\Pages\Page;

/**
 * Kitchen board (KDS) for a tablet / TV: New · Cooking · Ready · On the way, live countdowns, one-tap buttons.
 */
class KitchenBoard extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-fire';
    protected static ?string $navigationLabel = 'Kitchen Board';
    protected static ?string $navigationGroup = 'Live';
    protected static ?int $navigationSort = 2;
    protected static ?string $title = 'Kitchen Board';
    protected static ?string $slug = 'kitchen';
    protected static string $view = 'filament.pages.kitchen-board';

    public string $type = 'all';

    public static function canAccess(): bool
    {
        return (bool) auth()->user()?->hasRole(['owner', 'manager', 'staff']);
    }

    public function getMaxContentWidth(): ?string
    {
        return 'full';
    }

    public static function getNavigationBadge(): ?string
    {
        try {
            $n = Order::whereIn('status', ['pending', 'accepted'])->count();
        } catch (\Throwable $e) {
            return null;
        }
        return $n ? (string) $n : null;
    }

    public function accept(int $id, int $minutes): void
    {
        $o = Order::find($id);
        if (! $o || $o->status !== 'pending') return;
        $o->advance($minutes);
        Notification::make()->title("#" . substr($o->order_number, -6) . " accepted — ready in {$minutes} min")->success()->send();
    }

    public function next(int $id): void
    {
        $o = Order::find($id);
        if (! $o || $o->status === 'pending' || ! $o->nextStep()) return;
        $o->advance();
        Notification::make()->title("#" . substr($o->order_number, -6) . ': ' . $o->statusText())->success()->send();
    }

    public function reject(int $id, string $reason): void
    {
        $o = Order::find($id);
        if (! $o || ! in_array($o->status, ['pending', 'accepted'], true)) return;
        $o->reject(array_key_exists($reason, Order::REJECT_REASONS) ? $reason : 'other');
        Notification::make()->title("#" . substr($o->order_number, -6) . ' rejected')
            ->body($o->isPaidViaRazorpay() ? 'Paid online — refund it from the order page.' : null)->warning()->send();
    }

    protected function getViewData(): array
    {
        Order::autoRejectStale();
        $orders = Order::with('items')
            ->whereIn('status', ['pending', 'accepted', 'preparing', 'out_for_delivery'])
            ->where('created_at', '>=', now()->subDay())
            ->when($this->type !== 'all', fn ($q) => $q->where('order_type', $this->type))
            ->orderBy('created_at')
            ->get();

        $cols = [
            ['key' => 'pending', 'title' => '🆕 NEW', 'color' => '#DC2626', 'bg' => '#FEF2F2'],
            ['key' => 'accepted', 'title' => '🍳 COOKING', 'color' => '#B45309', 'bg' => '#FFFBEB'],
            ['key' => 'preparing', 'title' => '✅ READY / SERVED', 'color' => '#15803D', 'bg' => '#F0FDF4'],
            ['key' => 'out_for_delivery', 'title' => '🛵 ON THE WAY', 'color' => '#1D4ED8', 'bg' => '#EFF6FF'],
        ];
        foreach ($cols as &$c) {
            $c['orders'] = $orders->where('status', $c['key'])->values();
        }
        $counts = Order::whereIn('status', ['pending', 'accepted', 'preparing', 'out_for_delivery'])->where('created_at', '>=', now()->subDay())
            ->selectRaw('order_type, count(*) as n')->groupBy('order_type')->pluck('n', 'order_type')->all();

        return ['columns' => $cols, 'counts' => $counts, 'viewUrl' => fn ($o) => OrderResource::getUrl('view', ['record' => $o])];
    }
}
