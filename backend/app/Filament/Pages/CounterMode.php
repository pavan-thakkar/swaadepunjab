<?php

namespace App\Filament\Pages;

use App\Models\Category;
use App\Models\MenuItem;
use App\Models\Order;
use App\Services\OrderPricing;
use Filament\Notifications\Notification;
use Filament\Pages\Page;

/** Fast counter billing: tap dishes, choose Dine In / Take Away, take cash or UPI, print bill + kitchen ticket. */
class CounterMode extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-calculator';
    protected static ?string $navigationLabel = 'Counter mode';
    protected static ?string $navigationGroup = 'Live';
    protected static ?int $navigationSort = 2;
    protected static ?string $title = 'Counter';
    protected static string $view = 'filament.pages.counter-mode';

    public string $search = '';
    public string $category = 'all';
    /** @var array<int,int> menu_item_id => qty */
    public array $bill = [];
    public string $orderType = 'dine_in';
    public string $table = '';
    public string $phone = '';
    public string $customerName = '';
    public string $note = '';
    public string $payment = 'cash';
    public ?int $lastOrderId = null;

    public static function canAccess(): bool
    {
        return (bool) auth()->user()?->hasRole(['owner', 'manager', 'staff']);
    }

    public function getMaxContentWidth(): ?string
    {
        return 'full';
    }

    public function add(int $id): void
    {
        $dish = MenuItem::find($id);
        if (! $dish || ! $dish->is_available) {
            Notification::make()->title('This dish is sold out')->warning()->send();
            return;
        }
        $this->bill[$id] = min(50, ($this->bill[$id] ?? 0) + 1);
        $this->lastOrderId = null;
    }

    public function minus(int $id): void
    {
        if (! isset($this->bill[$id])) return;
        $this->bill[$id]--;
        if ($this->bill[$id] <= 0) unset($this->bill[$id]);
    }

    public function clearBill(): void
    {
        $this->reset(['bill', 'table', 'phone', 'customerName', 'note', 'search']);
        $this->payment = 'cash';
    }

    public function acceptOnline(int $orderId): void
    {
        $o = Order::find($orderId);
        if ($o && $o->status === 'pending') {
            $prep = 20;
            $o->update(['status' => 'accepted', 'prep_time_minutes' => $prep, 'estimated_delivery_at' => now()->addMinutes($prep + (($o->order_type ?? 'delivery') === 'delivery' ? 15 : 0))]);
            Notification::make()->title("Accepted {$o->order_number}")->success()->send();
        }
    }

    /** Lines + totals for the current bill, priced from the database. */
    protected function priced(): array
    {
        $lines = [];
        $subtotal = 0.0;
        if ($this->bill) {
            $dishes = MenuItem::whereIn('id', array_keys($this->bill))->get()->keyBy('id');
            foreach ($this->bill as $id => $qty) {
                $d = $dishes[$id] ?? null;
                if (! $d) continue;
                $line = (float) $d->price * $qty;
                $subtotal += $line;
                $lines[] = ['id' => $d->id, 'name' => $d->name, 'price' => (float) $d->price, 'qty' => $qty, 'total' => $line];
            }
        }
        $pricing = $subtotal > 0 ? OrderPricing::calculate($subtotal, null, $this->phone ?: null) : null;
        $discount = $pricing ? (float) $pricing['offer_discount'] : 0.0;
        return [
            'lines' => $lines,
            'subtotal' => $subtotal,
            'discount' => $discount,
            'freeLine' => $pricing['free_line'] ?? null,
            'offer' => $pricing['offer'] ?? null,
            'total' => max(0, $subtotal - $discount),
            'pricing' => $pricing,
        ];
    }

    public function placeOrder(): void
    {
        $p = $this->priced();
        if (! $p['lines']) {
            Notification::make()->title('Add at least one dish')->warning()->send();
            return;
        }
        $digits = preg_replace('/\D/', '', $this->phone);
        if ($this->phone !== '' && strlen($digits) < 10) {
            Notification::make()->title('Phone number looks incomplete')->warning()->send();
            return;
        }

        $items = array_map(fn ($l) => [
            'menu_item_id' => $l['id'], 'name' => $l['name'], 'price' => $l['price'], 'quantity' => $l['qty'], 'subtotal' => $l['total'],
        ], $p['lines']);
        if ($p['freeLine']) {
            $items[] = $p['freeLine'];
        }

        $prep = 15;
        $order = Order::create([
            'order_number'          => Order::generateOrderNumber(),
            'customer_name'         => trim($this->customerName) ?: ($this->orderType === 'dine_in' ? 'Dine-in guest' : 'Walk-in customer'),
            'customer_phone'        => $digits ? substr($digits, -10) : 'Counter',
            'order_type'            => $this->orderType,
            'table_number'          => $this->orderType === 'dine_in' ? (trim($this->table) ?: null) : null,
            'pickup_time'           => null,
            'delivery_address'      => $this->orderType === 'dine_in' ? ('Dine In' . ($this->table ? ' · Table ' . trim($this->table) : '')) : 'Take Away (counter)',
            'city'                  => 'Counter',
            'subtotal'              => $p['subtotal'],
            'delivery_fee'          => 0,
            'discount'              => $p['discount'],
            'offer_id'              => $p['offer']?->id,
            'offer_title'           => $p['offer']?->title,
            'total'                 => $p['total'],
            'status'                => 'accepted',
            'payment_method'        => $this->payment === 'upi' ? 'upi_counter' : 'cash_on_delivery',
            'special_instructions'  => trim($this->note) ?: null,
            'prep_time_minutes'     => $prep,
            'estimated_delivery_at' => now()->addMinutes($prep),
        ]);
        $order->items()->createMany($items);

        $this->lastOrderId = $order->id;
        $this->reset(['bill', 'table', 'phone', 'customerName', 'note', 'search']);
        $this->payment = 'cash';
        Notification::make()->title("Order {$order->order_number} placed")->success()->send();
        $this->dispatch('sp-print', url: route('admin.order.print', ['order' => $order->id, 'type' => 'both']));
    }

    protected function getViewData(): array
    {
        $cats = Category::orderBy('sort_order')->orderBy('name')->get(['name', 'slug']);
        $q = trim($this->search);
        $menu = MenuItem::query()
            ->when($this->category === 'bestsellers', fn ($x) => $x->where('is_featured', true))
            ->when(! in_array($this->category, ['all', 'bestsellers'], true), fn ($x) => $x->where('category', $this->category))
            ->when($q !== '', function ($x) use ($q) {
                $x->where(function ($w) use ($q) {
                    $w->where('name', 'like', '%' . $q . '%');
                    // initials search: "pt" → Paneer Tikka
                    if (mb_strlen($q) <= 4 && ! str_contains($q, ' ')) {
                        $w->orWhere(function ($ww) use ($q) {
                            $chars = preg_split('//u', mb_strtolower($q), -1, PREG_SPLIT_NO_EMPTY);
                            $pattern = implode('% ', $chars);
                            $ww->whereRaw('LOWER(name) LIKE ?', [$chars[0] . '%' . ltrim(substr($pattern, 1), '%') . '%']);
                        });
                    }
                });
            })
            ->orderByDesc('is_available')->orderByDesc('is_featured')->orderBy('name')
            ->limit(60)
            ->get(['id', 'name', 'price', 'is_available', 'is_featured']);

        $pending = Order::where('status', 'pending')->orderBy('created_at')->limit(6)->get(['id', 'order_number', 'order_type', 'total']);

        return array_merge($this->priced(), [
            'cats' => $cats,
            'menu' => $menu,
            'pending' => $pending,
            'lastOrder' => $this->lastOrderId ? Order::find($this->lastOrderId) : null,
        ]);
    }
}
