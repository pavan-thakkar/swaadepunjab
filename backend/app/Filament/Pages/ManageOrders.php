<?php

namespace App\Filament\Pages;

use App\Filament\Resources\OrderResource;
use App\Models\Order;
use Filament\Notifications\Notification;
use Filament\Pages\Page;

/**
 * Swiggy-style "Manage Orders": tabs New · Preparing · Ready · Picked up · Past orders on the left,
 * the selected order's full details on the right (timeline, items, customer, rider, bill, print, next step).
 */
class ManageOrders extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-queue-list';
    protected static ?string $navigationLabel = 'Manage Orders';
    protected static ?string $navigationGroup = 'Live';
    protected static ?int $navigationSort = 0;
    protected static ?string $title = 'Manage Orders';
    protected static ?string $slug = 'manage-orders';
    protected static string $view = 'filament.pages.manage-orders';

    public string $tab = 'new';
    public string $search = '';
    public ?int $selected = null;

    public const TABS = [
        'new'       => ['New', ['pending']],
        'preparing' => ['Preparing', ['accepted']],
        'ready'     => ['Ready', ['preparing']],
        'picked'    => ['Picked up', ['out_for_delivery']],
        'past'      => ['Past orders', ['delivered', 'cancelled']],
    ];

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
            $n = Order::where('status', 'pending')->count();
        } catch (\Throwable $e) {
            return null;
        }
        return $n ? (string) $n : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'danger';
    }

    public function mount(): void
    {
        // Open on the busiest useful tab
        if (! Order::where('status', 'pending')->exists() && Order::where('status', 'accepted')->exists()) {
            $this->tab = 'preparing';
        }
    }

    public function setTab(string $t): void
    {
        if (! isset(self::TABS[$t])) return;
        $this->tab = $t;
        $this->selected = null;
    }

    public function select(int $id): void
    {
        $this->selected = $id;
    }

    public function back(): void
    {
        $this->selected = null;
    }

    protected function listQuery()
    {
        $statuses = self::TABS[$this->tab][1];
        $q = Order::query()->with('items')->whereIn('status', $statuses);
        if ($this->tab === 'past') {
            $q->where('updated_at', '>=', now()->subDay())->orderByDesc('updated_at');
        } else {
            $q->where('created_at', '>=', now()->subDays(2))->orderBy('created_at');
        }
        $s = trim($this->search);
        if ($s !== '') {
            $q->where(fn ($w) => $w->where('order_number', 'like', "%{$s}%")->orWhere('customer_name', 'like', "%{$s}%")->orWhere('customer_phone', 'like', "%{$s}%"));
        }
        return $q;
    }

    // ---------------- actions on the selected order ----------------

    protected function order(int $id): ?Order
    {
        return Order::with('items')->find($id);
    }

    public function accept(int $id, int $minutes): void
    {
        $o = $this->order($id);
        if (! $o || $o->status !== 'pending') return;
        $o->advance($minutes);
        Notification::make()->title('#' . substr($o->order_number, -4) . " accepted — ready in {$minutes} min")->success()->send();
        $this->tab = 'preparing';
    }

    public function next(int $id): void
    {
        $o = $this->order($id);
        if (! $o || $o->status === 'pending' || ! $o->nextStep()) return;
        $o->advance();
        Notification::make()->title('#' . substr($o->order_number, -4) . ': ' . $o->statusText())->success()->send();
        $this->tab = match ($o->status) { 'accepted' => 'preparing', 'preparing' => 'ready', 'out_for_delivery' => 'picked', default => 'past' };
    }

    public function reject(int $id, string $reason): void
    {
        $o = $this->order($id);
        if (! $o || ! in_array($o->status, ['pending', 'accepted'], true)) return;
        $o->reject(array_key_exists($reason, Order::REJECT_REASONS) ? $reason : 'other');
        Notification::make()->title('#' . substr($o->order_number, -4) . ' rejected')
            ->body($o->isPaidViaRazorpay() ? 'Paid online — refund it from the full order page.' : null)->warning()->send();
    }

    public function assignRider(int $id, $riderId): void
    {
        $o = $this->order($id);
        if (! $o || $o->type() !== 'delivery') return;
        $o->assignRider($riderId ? (int) $riderId : null);
        Notification::make()->title($o->rider_name ? "Given to {$o->rider_name} 🛵" : 'Delivery boy removed')->success()->send();
    }

    public function settle(int $id, string $mode): void
    {
        $o = $this->order($id);
        if (! $o || ! in_array($mode, ['cash', 'upi', 'card'], true) || ! $o->needsSettlement()) return;
        $o->settle($mode);
        Notification::make()->title('Bill settled — ' . $o->settleLabel())->success()->send();
    }

    /** Big switch at the top: accept home-delivery orders or not (same as Dashboard → Quick switches). */
    public function toggleOnline(): void
    {
        $st = \App\Models\ReceiptSetting::getSettings();
        $st->update(['delivery_enabled' => ! $st->delivery_enabled]);
        Notification::make()->title($st->delivery_enabled ? 'You are ONLINE — delivery orders are open' : 'You are OFFLINE — website delivery is closed (Take Away / Dine In still open)')
            ->{$st->delivery_enabled ? 'success' : 'warning'}()->send();
    }

    protected function getViewData(): array
    {
        Order::autoRejectStale();
        $since = now()->subDays(2);
        $counts = [];
        foreach (self::TABS as $k => [$label, $st]) {
            $counts[$k] = $k === 'past'
                ? Order::whereIn('status', $st)->where('updated_at', '>=', now()->subDay())->count()
                : Order::whereIn('status', $st)->where('created_at', '>=', $since)->count();
        }
        $list = $this->listQuery()->limit(80)->get();

        $sel = $this->selected ? Order::with('items.menuItem')->find($this->selected) : null;
        if (! $sel && $list->isNotEmpty()) {
            $sel = $list->first()?->load('items.menuItem'); // show the first order straight away (big screens)
        }
        $history = $sel && preg_match('/\d{10}/', (string) $sel->customer_phone)
            ? Order::where('customer_phone', $sel->customer_phone)->whereNotIn('status', ['cancelled', 'awaiting_payment'])->where('created_at', '>=', now()->subDays(30))->count()
            : 0;

        $st = \App\Models\ReceiptSetting::getSettings();
        return [
            'online' => (bool) ($st->delivery_enabled ?? true),
            'outletName' => $st->outlet_name ?: 'Swaad E Punjab',
            'outletAddress' => $st->outlet_address,
            'catNames' => \App\Models\Category::pluck('name', 'slug')->all(),
            'counts' => $counts, 'list' => $list, 'sel' => $sel, 'history' => $history,
            'riders' => Order::riderOptions(),
            'viewUrl' => fn ($o) => OrderResource::getUrl('view', ['record' => $o]),
            'printUrl' => fn ($o, $t) => route('admin.order.print', ['order' => $o->id, 'type' => $t]),
        ];
    }
}
