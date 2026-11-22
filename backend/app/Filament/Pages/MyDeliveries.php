<?php

namespace App\Filament\Pages;

use App\Models\Order;
use App\Models\ReceiptSetting;
use App\Services\Razorpay;
use Filament\Notifications\Notification;
use Filament\Pages\Page;

/**
 * Delivery boy's phone screen: his orders with customer call / WhatsApp, Google Maps navigation,
 * amount to collect, and buttons for Picked up · Cash · Shop UPI QR · Pay online (Razorpay QR) · Delivered.
 * Owner / manager see every rider's deliveries here too.
 */
class MyDeliveries extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-truck';
    protected static ?string $navigationGroup = 'Live';
    protected static ?int $navigationSort = 4;
    protected static ?string $slug = 'my-deliveries';
    protected static string $view = 'filament.pages.my-deliveries';

    public string $tab = 'todo';
    /** Order id whose payment QR is open, and which kind ('online' | 'upi'). */
    public ?int $qrOrder = null;
    public string $qrKind = '';

    public static function canAccess(): bool
    {
        return (bool) auth()->user()?->hasRole(['rider', 'owner', 'manager']);
    }

    public static function getNavigationLabel(): string
    {
        return auth()->user()?->hasRole('rider') ? 'My Deliveries' : 'Deliveries (riders)';
    }

    public function getTitle(): string
    {
        return auth()->user()?->hasRole('rider') ? 'My Deliveries' : 'Deliveries';
    }

    public static function getNavigationBadge(): ?string
    {
        try {
            $n = static::baseQuery()->whereNotIn('status', ['delivered', 'cancelled'])->count();
        } catch (\Throwable $e) {
            return null;
        }
        return $n ? (string) $n : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }

    protected static function isRider(): bool
    {
        return (bool) auth()->user()?->hasRole('rider');
    }

    /** A rider sees only his own orders; owner / manager see all orders that have a rider. */
    protected static function baseQuery()
    {
        $q = Order::query()->with('items')->where('order_type', 'delivery')->whereNotNull('rider_id');
        return static::isRider() ? $q->where('rider_id', auth()->id()) : $q;
    }

    protected function find(int $id): ?Order
    {
        $o = static::baseQuery()->find($id);
        if (! $o) Notification::make()->title('This order is not yours any more')->warning()->send();
        return $o;
    }

    public function pickedUp(int $id): void
    {
        $o = $this->find($id);
        if (! $o || in_array($o->status, ['delivered', 'cancelled', 'out_for_delivery'], true)) return;
        $o->update(['status' => 'out_for_delivery']);
        Notification::make()->title('On the way 🛵 — ' . $o->order_number)->success()->send();
    }

    /** Rider took cash, or customer paid to the shop UPI QR. */
    public function collected(int $id, string $mode): void
    {
        $o = $this->find($id);
        if (! $o || ! in_array($mode, ['cash', 'upi'], true)) return;
        if (! $o->needsSettlement()) {
            Notification::make()->title('Already paid')->success()->send();
            $this->closeQr();
            return;
        }
        $o->settle($mode, [], $mode === 'upi' ? 'Paid to shop UPI at the door — check the bank app' : 'Cash collected by delivery boy');
        Notification::make()->title(($mode === 'cash' ? '💵 Cash ₹' : '📱 UPI ₹') . number_format((float) $o->total, 0) . ' noted')->success()->send();
        $this->closeQr();
    }

    public function showUpi(int $id): void
    {
        $o = $this->find($id);
        if (! $o) return;
        if (! ReceiptSetting::query()->value('shop_upi_id')) {
            Notification::make()->title('Shop UPI ID is not set')->body('Owner: add it in Store & Receipt Config → Shop UPI.')->warning()->send();
            return;
        }
        $this->qrOrder = $id; $this->qrKind = 'upi';
    }

    public function payOnline(int $id): void
    {
        $o = $this->find($id);
        if (! $o) return;
        if (! $o->needsSettlement()) {
            Notification::make()->title('Already paid')->success()->send();
            return;
        }
        if (! Razorpay::payLinkFor($o)) {
            Notification::make()->title('Could not make the payment link')->body('Razorpay keys are missing or Razorpay did not answer. Use the shop UPI QR instead.')->danger()->send();
            return;
        }
        $this->qrOrder = $id; $this->qrKind = 'online';
    }

    /** Called every few seconds while the online QR is open, and by the "Check payment" button. */
    public function checkPayment(bool $manual = false): void
    {
        if (! $this->qrOrder || $this->qrKind !== 'online') return;
        $o = static::baseQuery()->find($this->qrOrder);
        if (! $o) { $this->closeQr(); return; }
        $r = Razorpay::syncPayLink($o);
        if ($r === 'paid') {
            Notification::make()->title('✅ Paid online — ₹' . number_format((float) $o->total, 0))->body('Nothing to collect. Hand over the food and tap Delivered.')->success()->send();
            $this->closeQr();
        } elseif ($manual) {
            Notification::make()->title($r === 'pending' ? 'Not paid yet — ask the customer to finish payment' : 'Could not reach Razorpay, try again')->warning()->send();
        }
    }

    public function sendLinkWhatsApp(int $id): void
    {
        $o = $this->find($id);
        if (! $o || ! $o->pay_link_url) return;
        $ok = false;
        try {
            $ok = \App\Services\WhatsAppGateway::configured()
                && \App\Services\WhatsAppGateway::send($o->customer_phone, "Pay ₹" . number_format((float) $o->total, 0) . " for your Swaad E Punjab order {$o->order_number}:\n{$o->pay_link_url}");
        } catch (\Throwable $e) {
            $ok = false;
        }
        Notification::make()->title($ok ? 'Link sent on WhatsApp' : 'Auto-send is not set up — use the green WhatsApp button')->{$ok ? 'success' : 'warning'}()->send();
    }

    public function closeQr(): void
    {
        $this->qrOrder = null; $this->qrKind = '';
    }

    public function delivered(int $id): void
    {
        $o = $this->find($id);
        if (! $o || in_array($o->status, ['delivered', 'cancelled'], true)) return;
        if ($o->needsSettlement()) {
            Notification::make()->title('Collect the payment first')->body('Tap Cash, Shop UPI or Pay online — then Delivered.')->danger()->send();
            return;
        }
        $o->update(['status' => 'delivered']);
        Notification::make()->title('🎉 Delivered — ' . $o->order_number)->success()->send();
    }

    protected function getViewData(): array
    {
        $todo = static::baseQuery()->whereNotIn('status', ['delivered', 'cancelled', 'awaiting_payment'])
            ->orderByRaw("CASE status WHEN 'out_for_delivery' THEN 0 WHEN 'preparing' THEN 1 ELSE 2 END")->orderBy('created_at')->get();
        $done = static::baseQuery()->where('status', 'delivered')->where('delivered_at', '>=', today())->orderByDesc('delivered_at')->get();
        $qr = $this->qrOrder ? static::baseQuery()->find($this->qrOrder) : null;
        $s = ReceiptSetting::query()->first();
        $upiUri = null;
        if ($qr && $this->qrKind === 'upi' && $s?->shop_upi_id) {
            $upiUri = 'upi://pay?' . http_build_query(['pa' => $s->shop_upi_id, 'pn' => $s->shop_upi_name ?: ($s->outlet_name ?: 'Swaad E Punjab'),
                'am' => number_format((float) $qr->total, 2, '.', ''), 'cu' => 'INR', 'tn' => 'Order ' . $qr->order_number], '', '&', PHP_QUERY_RFC3986);
        }
        return [
            'todo' => $todo, 'done' => $done, 'qr' => $qr, 'upiUri' => $upiUri, 'isRider' => static::isRider(),
            'cashInHand' => static::isRider() ? (float) Order::where('rider_id', auth()->id())->where('settle_mode', 'cash')->where('settled_at', '>=', today())->sum('settle_cash') : null,
            'shopPhone' => $s?->outlet_phone,
        ];
    }
}
