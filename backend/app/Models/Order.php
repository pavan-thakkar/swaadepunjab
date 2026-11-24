<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Order extends Model
{
    protected $fillable = [
        'accepted_at',
        'ready_at',
        'rider_id',
        'rider_name',
        'rider_assigned_at',
        'picked_up_at',
        'delivered_at',
        'pay_link_id',
        'pay_link_url',
        'cancelled_by',
        'cancelled_by_name',
        'cancelled_at',
        'cancel_reason',
        'settle_mode',
        'settle_cash',
        'settle_upi',
        'settle_card',
        'settled_at',
        'settled_by',
        'settled_by_name',
        'settle_note',
        'order_rating',
        'order_feedback',
        'rated_at',
        'rating_link_sent_at',
        'feedback_called_at',
        'feedback_called_by',
        'feedback_note',
        'refund_amount',
        'refunded_at',
        'razorpay_refund_id',
        'refund_note',
        'gst_amount',
        'gst_rate',
        'gst_mode',
        'payment_status',
        'razorpay_order_id',
        'razorpay_payment_id',
        'paid_at',
        'order_number',
        'customer_name',
        'customer_email',
        'customer_phone',
        'delivery_address',
        'city',
        'subtotal',
        'delivery_fee',
        'total',
        'status',
        'payment_method',
        'special_instructions',
        'estimated_delivery_at',
        'prep_time_minutes',
        'latitude',
        'longitude',
        'order_type',
        'table_number',
        'pickup_time',
        'distance_km',
        'edit_token',
        'edited_at',
        'discount',
        'offer_id',
        'offer_title',
        'coupon_code',
        'coupon_discount',
        'points_redeemed',
        'points_discount',
    ];

    /** Fallback if the admin setting is missing. */
    public const EDIT_WINDOW_MINUTES = 2;

    private static ?int $editMinutesCache = null;

    /** Minutes a customer may change an order after placing it (Admin → Store & Receipt Config). 0 = off. */
    public static function editWindowMinutes(): int
    {
        if (self::$editMinutesCache === null) {
            try {
                $value = \App\Models\ReceiptSetting::query()->value('order_edit_minutes');
                self::$editMinutesCache = $value === null ? self::EDIT_WINDOW_MINUTES : max(0, min(60, (int) $value));
            } catch (\Throwable $e) {
                self::$editMinutesCache = self::EDIT_WINDOW_MINUTES;
            }
        }
        return self::$editMinutesCache;
    }

    protected $hidden = ['edit_token'];

    protected $appends = ['editable_until', 'can_edit'];

    protected $casts = [
        'paid_at' => 'datetime',
        'settled_at' => 'datetime',
        'cancelled_at' => 'datetime',
        'rider_assigned_at' => 'datetime',
        'accepted_at' => 'datetime',
        'ready_at' => 'datetime',
        'picked_up_at' => 'datetime',
        'delivered_at' => 'datetime',
        'settle_cash' => 'decimal:2',
        'settle_upi' => 'decimal:2',
        'settle_card' => 'decimal:2',
        'refunded_at' => 'datetime',
        'rated_at' => 'datetime',
        'rating_link_sent_at' => 'datetime',
        'feedback_called_at' => 'datetime',
        'order_rating' => 'integer',
        'refund_amount' => 'decimal:2',
        'subtotal'              => 'decimal:2',
        'delivery_fee'          => 'decimal:2',
        'total'                 => 'decimal:2',
        'estimated_delivery_at' => 'datetime',
        'latitude'              => 'float',
        'longitude'             => 'float',
        'distance_km'           => 'float',
        'edited_at'             => 'datetime',
        'discount'              => 'decimal:2',
        'coupon_discount'       => 'decimal:2',
        'points_discount'       => 'decimal:2',
        'gst_amount'            => 'decimal:2',
        'gst_rate'              => 'float',
        'points_redeemed'       => 'integer',
    ];

    protected static function booted(): void
    {
        // Remember who cancelled: a logged-in admin user → staff (with name); otherwise the customer.
        // System cancels (unpaid online orders) call cancelledBySystem() first, so they are kept as 'system'.
        static::saving(function (Order $order) {
            if (! $order->isDirty('status')) return;
            // Delivery timeline
            if ($order->status === 'accepted' && ! $order->accepted_at) $order->accepted_at = now();
            if ($order->status === 'preparing' && ! $order->ready_at) $order->ready_at = now();
            if ($order->status === 'out_for_delivery' && ! $order->picked_up_at) $order->picked_up_at = now();
            if ($order->status === 'delivered' && ! $order->delivered_at) $order->delivered_at = now();
            if ($order->status === 'cancelled') {
                if (! $order->isDirty('cancelled_by') || ! $order->cancelled_by) {
                    $user = auth()->guard('web')->user();
                    $order->cancelled_by = $user ? 'staff' : 'customer';
                    $order->cancelled_by_name = $user?->name;
                }
                $order->cancelled_at = $order->cancelled_at && $order->isDirty('cancelled_at') ? $order->cancelled_at : now();
            } elseif ($order->getOriginal('status') === 'cancelled') {
                // order brought back → forget the old cancel
                $order->cancelled_by = null; $order->cancelled_by_name = null; $order->cancelled_at = null; $order->cancel_reason = null;
            }
        });

        // Loyalty points: earn on delivery, give back on cancel
        static::updated(function (Order $order) {
            if ($order->wasChanged('status')) {
                try {
                    \App\Services\Loyalty::onStatusChanged($order);
                } catch (\Throwable $e) {
                    \Illuminate\Support\Facades\Log::error('Loyalty update failed: ' . $e->getMessage());
                }
                // Ask for a rating on WhatsApp after delivery (only when a WhatsApp gateway is set up)
                if ($order->status === 'delivered' && ! $order->rating_link_sent_at && ! $order->order_rating) {
                    try {
                        if (\App\Services\WhatsAppGateway::configured() && \App\Services\WhatsAppGateway::send($order->customer_phone, $order->ratingMessage())) {
                            $order->forceFill(['rating_link_sent_at' => now()])->saveQuietly();
                        }
                    } catch (\Throwable $e) {
                        \Illuminate\Support\Facades\Log::warning('Rating WhatsApp failed: ' . $e->getMessage());
                    }
                }
            }
        });
    }

    /**
     * The customer website address for links we send (rating, etc.).
     * A "localhost" address only makes sense on the developer laptop, so on the live server we never use one.
     */
    public static function websiteUrl(): string
    {
        $live = 'https://swaadepunjab.com';
        $url = trim((string) (config('app.frontend_url') ?: env('FRONTEND_URL', '')));
        $isLocalUrl = $url === '' || preg_match('#^https?://(localhost|127\.0\.0\.1|0\.0\.0\.0|\[::1\])(:\d+)?(/|$)#i', $url);
        if ($isLocalUrl) {
            $host = request()?->getHost() ?? '';
            $onLaptop = in_array($host, ['localhost', '127.0.0.1', '::1'], true) || app()->environment('local');
            return ($onLaptop && $url !== '') ? rtrim($url, '/') : $live;
        }
        return rtrim($url, '/');
    }

    /** Secret part of the rating link (works on any phone, no login needed). */
    public function ratingToken(): string
    {
        return substr(hash_hmac('sha256', 'rate|' . $this->id . '|' . $this->order_number, (string) config('app.key')), 0, 24);
    }

    public function ratingUrl(): string
    {
        $site = self::websiteUrl();
        return $site . '/rate/?o=' . urlencode((string) $this->order_number) . '&t=' . $this->ratingToken();
    }

    public function ratingMessage(): string
    {
        $first = trim(explode(' ', (string) $this->customer_name)[0] ?? '');
        return "Hi {$first}! 🙏 Thank you for ordering from Swaad E Punjab.\n\nHow was your food? Please rate your order (takes 10 seconds):\n" . $this->ratingUrl();
    }

    /** Low ratings that nobody has called about yet. */
    public static function badRatingsToCall(): int
    {
        try {
            return static::whereNotNull('order_rating')->where('order_rating', '<=', 2)->whereNull('feedback_called_at')->count();
        } catch (\Throwable $e) {
            return 0;
        }
    }

    public function getEditableUntilAttribute(): ?string
    {
        return $this->created_at?->copy()->addMinutes(self::editWindowMinutes())->toIso8601String();
    }

    public function getCanEditAttribute(): bool
    {
        return self::editWindowMinutes() > 0
            && $this->status === 'pending'
            && $this->created_at
            && now()->lte($this->created_at->copy()->addMinutes(self::editWindowMinutes()));
    }

    public function isPaidOnline(): bool
    {
        if ($this->payment_method === 'razorpay') {
            return $this->payment_status === 'paid';
        }
        return ! in_array($this->payment_method, ['cash_on_delivery', 'upi_counter', null, ''], true);
    }

    // ---------------- Settle Bill ----------------

    public const SETTLE_MODES = ['cash' => '💵 Cash', 'upi' => '📱 UPI', 'card' => '💳 Card', 'split' => '➗ Split', 'online' => '🌐 Online'];

    /** Paid on the website through Razorpay → counts as settled automatically. */
    public function isPaidViaRazorpay(): bool
    {
        return $this->payment_method === 'razorpay' && in_array($this->payment_status, ['paid', 'refunded'], true);
    }

    public function isSettled(): bool
    {
        return filled($this->settle_mode) || $this->isPaidViaRazorpay();
    }

    /** Needs a cashier to settle it: a real order (not cancelled / unpaid online) with no settlement yet. */
    public function needsSettlement(): bool
    {
        return ! in_array($this->status, ['cancelled', 'awaiting_payment'], true) && ! $this->isSettled();
    }

    public function scopeUnsettled($q)
    {
        return $q->whereNotIn('status', ['cancelled', 'awaiting_payment'])
            ->whereNull('settle_mode')
            ->where(fn ($w) => $w->where('payment_method', '!=', 'razorpay')->orWhereNull('payment_method')
                ->orWhereNotIn('payment_status', ['paid', 'refunded'])->orWhereNull('payment_status'));
    }

    /** Best guess of how the customer paid, used as the default in the Settle Bill form. */
    public function suggestedSettleMode(): string
    {
        return match ($this->payment_method) {
            'upi_counter' => 'upi',
            'card'        => 'card',
            default       => 'cash',
        };
    }

    public function settleLabel(): string
    {
        if ($this->settle_mode) return self::SETTLE_MODES[$this->settle_mode] ?? $this->settle_mode;
        if ($this->isPaidViaRazorpay()) return self::SETTLE_MODES['online'];
        return in_array($this->status, ['cancelled', 'awaiting_payment'], true) ? '—' : '⚠️ Unsettled';
    }

    /** Money by mode for this bill: ['cash'=>, 'upi'=>, 'card'=>, 'online'=>]. */
    public function settledSplit(): array
    {
        $out = ['cash' => 0.0, 'upi' => 0.0, 'card' => 0.0, 'online' => 0.0];
        if ($this->settle_mode) {
            $out['cash'] = (float) $this->settle_cash; $out['upi'] = (float) $this->settle_upi; $out['card'] = (float) $this->settle_card;
            if ($this->settle_mode === 'online') $out['online'] = (float) $this->total;
        } elseif ($this->isPaidViaRazorpay()) {
            $out['online'] = (float) $this->total;
        }
        return $out;
    }

    /** Record how this bill was paid. $split is only used for mode 'split'. */
    public function settle(string $mode, array $split = [], ?string $note = null, ?\App\Models\User $by = null): void
    {
        $by = $by ?? auth()->user();
        $total = round((float) $this->total, 2);
        $cash = $upi = $card = 0.0;
        match ($mode) {
            'cash'  => $cash = $total,
            'upi'   => $upi = $total,
            'card'  => $card = $total,
            'split' => [$cash, $upi, $card] = [round((float) ($split['cash'] ?? 0), 2), round((float) ($split['upi'] ?? 0), 2), round((float) ($split['card'] ?? 0), 2)],
            default => null,
        };
        $this->update([
            'settle_mode' => $mode, 'settle_cash' => $cash, 'settle_upi' => $upi, 'settle_card' => $card,
            'settled_at' => now(), 'settled_by' => $by?->id, 'settled_by_name' => $by?->name, 'settle_note' => $note ?: null,
        ]);
        \App\Models\ActivityLog::record('settle', "Settled bill {$this->order_number} ₹" . number_format($total, 2) . ' — ' . strip_tags(self::SETTLE_MODES[$mode] ?? $mode)
            . ($mode === 'split' ? " (cash ₹{$cash}, UPI ₹{$upi}, card ₹{$card})" : ''), $this, [], $by);
    }

    public function unsettle(): void
    {
        $was = $this->settleLabel();
        $this->update(['settle_mode' => null, 'settle_cash' => 0, 'settle_upi' => 0, 'settle_card' => 0, 'settled_at' => null, 'settled_by' => null, 'settled_by_name' => null, 'settle_note' => null]);
        \App\Models\ActivityLog::record('unsettle', "Undid settlement of bill {$this->order_number} (was {$was})", $this);
    }

    // ---------------- Swiggy-style order flow ----------------
    // Status values stay the same in the database; 'preparing' means FOOD READY (it always did).
    //   Delivery : pending → accepted (cooking) → preparing (food ready) → out_for_delivery (picked up) → delivered
    //   Take Away: pending → accepted (cooking) → preparing (ready for pickup) → delivered (handed over)
    //   Dine In  : pending → accepted (cooking) → preparing (served)          → delivered (completed)

    public const REJECT_REASONS = [
        'item_finished' => 'Item(s) finished / sold out',
        'busy'          => 'Kitchen too busy right now',
        'closed'        => 'Restaurant closed / closing',
        'no_rider'      => 'No delivery boy available / too far',
        'other'         => 'Other reason',
    ];

    public const PREP_CHOICES = [10, 15, 20, 30, 45];

    public function type(): string
    {
        return $this->order_type ?: 'delivery';
    }

    /** Status in words for this order type. */
    public function statusText(): string
    {
        $t = $this->type();
        return match ($this->status) {
            'awaiting_payment' => '💳 Awaiting payment',
            'pending'          => '🆕 New order',
            'accepted'         => '🍳 Cooking',
            'preparing'        => match ($t) { 'takeaway' => '🛍️ Ready for pickup', 'dine_in' => '🍽️ Served', default => '✅ Food ready' },
            'out_for_delivery' => '🛵 On the way',
            'delivered'        => match ($t) { 'takeaway' => '✅ Handed over', 'dine_in' => '✅ Completed', default => '🎉 Delivered' },
            'cancelled'        => '❌ Cancelled',
            default            => (string) $this->status,
        };
    }

    /** The one next step for this order: ['to', 'label', 'color'] (null when finished / cancelled / waiting for payment). */
    public function nextStep(): ?array
    {
        $t = $this->type();
        return match ($this->status) {
            'pending'          => ['to' => 'accepted', 'label' => 'Accept', 'color' => 'success'],
            'accepted'         => ['to' => 'preparing', 'label' => match ($t) { 'takeaway' => 'Ready for pickup', 'dine_in' => 'Served', default => 'Food ready' }, 'color' => 'warning'],
            'preparing'        => match ($t) {
                'takeaway' => ['to' => 'delivered', 'label' => 'Handed over', 'color' => 'success'],
                'dine_in'  => ['to' => 'delivered', 'label' => 'Completed', 'color' => 'success'],
                default    => ['to' => 'out_for_delivery', 'label' => $this->rider_name ? 'Picked up by ' . strtok($this->rider_name, ' ') : 'Handed to rider', 'color' => 'info'],
            },
            'out_for_delivery' => ['to' => 'delivered', 'label' => 'Delivered', 'color' => 'success'],
            default            => null,
        };
    }

    /** Move to the next step. Accepting takes the prep time (minutes) for the countdown. */
    public function advance(?int $prepMinutes = null): ?string
    {
        $step = $this->nextStep();
        if (! $step) return null;
        $data = ['status' => $step['to']];
        if ($step['to'] === 'accepted') {
            $prep = max(5, min(120, (int) ($prepMinutes ?: $this->prep_time_minutes ?: 20)));
            $data['prep_time_minutes'] = $prep;
            $data['estimated_delivery_at'] = now()->addMinutes($prep + ($this->type() === 'delivery' ? 15 : 0));
        }
        $this->update($data);
        return $step['to'];
    }

    /** When the food should be ready (accepted time + prep time). */
    public function readyDueAt(): ?\Carbon\Carbon
    {
        if (! in_array($this->status, ['accepted'], true)) return null;
        $start = $this->accepted_at ?: $this->updated_at ?: $this->created_at;
        return $start?->copy()->addMinutes((int) ($this->prep_time_minutes ?: 20));
    }

    /** Small live timer for staff: ['text', 'state' => ok|warn|late, 'due' => unix seconds or null]. */
    public function timer(): ?array
    {
        $now = now();
        switch ($this->status) {
            case 'pending':
                $m = (int) floor($this->created_at->diffInSeconds($now) / 60);
                return ['text' => $m < 1 ? 'Just now — accept quickly' : "Waiting {$m} min — accept quickly", 'state' => $m >= 3 ? 'late' : ($m >= 1 ? 'warn' : 'ok'), 'due' => null, 'since' => $this->created_at->timestamp];
            case 'accepted':
                $due = $this->readyDueAt();
                $left = (int) ceil(($due->timestamp - $now->timestamp) / 60);
                return ['text' => $left >= 0 ? "⏱ {$left} min left" : '🔴 ' . abs($left) . ' min late', 'state' => $left < 0 ? 'late' : ($left <= 3 ? 'warn' : 'ok'), 'due' => $due->timestamp, 'since' => null];
            case 'preparing':
                $start = $this->ready_at ?: $this->updated_at;
                $m = (int) floor($start->diffInSeconds($now) / 60);
                $what = match ($this->type()) { 'takeaway' => 'waiting for customer', 'dine_in' => 'on the table', default => $this->rider_name ? 'waiting for ' . strtok($this->rider_name, ' ') : 'no rider yet' };
                return ['text' => "Ready {$m} min · {$what}", 'state' => $m >= 10 && $this->type() !== 'dine_in' ? 'late' : 'ok', 'due' => null, 'since' => $start->timestamp];
            case 'out_for_delivery':
                $start = $this->picked_up_at ?: $this->updated_at;
                $m = (int) floor($start->diffInSeconds($now) / 60);
                return ['text' => "On the way {$m} min", 'state' => $m >= 40 ? 'late' : 'ok', 'due' => null, 'since' => $start->timestamp];
        }
        return null;
    }

    /** Reject a new order with a reason; "item finished" can switch those dishes to sold out. */
    public function reject(string $reason, array $soldOutMenuItemIds = [], ?string $note = null): void
    {
        $text = self::REJECT_REASONS[$reason] ?? $reason;
        $this->cancel_reason = trim($text . ($note ? ' — ' . $note : ''));
        $this->update(['status' => 'cancelled']);
        if ($reason === 'item_finished' && $soldOutMenuItemIds) {
            \App\Models\MenuItem::whereIn('id', $soldOutMenuItemIds)->update(['is_available' => false]);
        }
    }

    /** Minutes after which a new order nobody accepted is rejected automatically (0 = off). */
    public static function autoRejectMinutes(): int
    {
        try {
            return max(0, (int) \App\Models\ReceiptSetting::query()->value('auto_reject_minutes'));
        } catch (\Throwable $e) {
            return 0;
        }
    }

    /** Reject new orders nobody accepted in time. Paid-online orders are never auto-rejected (they need a refund decision). */
    public static function autoRejectStale(): int
    {
        static $done = false;
        if ($done) return 0;
        $done = true;
        $min = self::autoRejectMinutes();
        if ($min <= 0) return 0;
        $n = 0;
        try {
            self::where('status', 'pending')->where('created_at', '<', now()->subMinutes($min))
                ->where(fn ($q) => $q->where('payment_method', '!=', 'razorpay')->orWhereNull('payment_method')->orWhere('payment_status', '!=', 'paid')->orWhereNull('payment_status'))
                ->get()->each(function (self $o) use ($min, &$n) {
                    if ($o->coupon_code) \App\Models\Coupon::findByCode($o->coupon_code)?->decrement('used_count');
                    $o->cancelledBySystem("Restaurant did not accept within {$min} min")->update(['status' => 'cancelled']);
                    $n++;
                });
        } catch (\Throwable $e) {
            report($e);
        }
        return $n;
    }

    // ---------------- Delivery boys ----------------

    /** Active delivery-boy accounts, for the "Assign rider" picker. */
    public static function riderOptions(): array
    {
        return \App\Models\User::where('role', 'rider')->where('is_active', true)->orderBy('name')->get()
            ->mapWithKeys(fn ($u) => [$u->id => $u->name . ($u->phone ? " · {$u->phone}" : '')])->all();
    }

    public function rider(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'rider_id');
    }

    public function assignRider(?int $riderId): void
    {
        $r = $riderId ? \App\Models\User::where('role', 'rider')->find($riderId) : null;
        $this->update(['rider_id' => $r?->id, 'rider_name' => $r?->name, 'rider_assigned_at' => $r ? now() : null]);
        \App\Models\ActivityLog::record('rider', $r ? "Gave order {$this->order_number} to {$r->name} for delivery" : "Removed delivery boy from order {$this->order_number}", $this);
    }

    /** Google Maps directions to the customer: exact GPS pin if we have it, otherwise the typed address. */
    public function mapsUrl(): string
    {
        if ($this->latitude && $this->longitude) {
            return "https://www.google.com/maps/dir/?api=1&destination={$this->latitude},{$this->longitude}";
        }
        return 'https://www.google.com/maps/dir/?api=1&destination=' . rawurlencode(trim($this->delivery_address . ($this->city && $this->city !== 'Counter' ? ', ' . $this->city : '')));
    }

    /** Amount the delivery boy must collect (0 if already paid / settled). */
    public function amountToCollect(): float
    {
        return $this->needsSettlement() ? round((float) $this->total, 2) : 0.0;
    }

    /** Mark the next cancel as done by the system (e.g. online payment not completed). Call before update(['status' => 'cancelled']). */
    public function cancelledBySystem(string $reason): static
    {
        $this->cancelled_by = 'system';
        $this->cancelled_by_name = null;
        $this->cancel_reason = $reason;
        return $this;
    }

    /** Short text for admin screens: "by customer", "by Ramesh (staff)", "Auto: payment not completed". */
    public function cancelledByLabel(): ?string
    {
        if ($this->status !== 'cancelled') return null;
        $t = $this->cancelled_at?->timezone(config('app.timezone'))->format('d M, h:i A');
        $who = match ($this->cancelled_by) {
            'customer' => '👤 by customer',
            'staff'    => '🧑‍🍳 by ' . ($this->cancelled_by_name ?: 'admin') . ' (admin)' . ($this->cancel_reason ? ' — ' . $this->cancel_reason : ''),
            'system'   => '⚙️ Auto' . ($this->cancel_reason ? ': ' . lcfirst($this->cancel_reason) : ''),
            default    => null,
        };
        if (! $who) return null;
        return $who . ($t ? " · {$t}" : '');
    }

    /** Online orders whose payment was never finished are cancelled after this many minutes. */
    public const PAYMENT_TIMEOUT_MINUTES = 30;

    /** Cancel unpaid online orders that are too old (loyalty points come back automatically). */
    public static function expireUnpaid(): void
    {
        static $done = false;
        if ($done) return;
        $done = true;
        try {
            self::where('status', 'awaiting_payment')
                ->where('created_at', '<', now()->subMinutes(self::PAYMENT_TIMEOUT_MINUTES))
                ->get()
                ->each(function (self $o) {
                    if ($o->coupon_code) {
                        \App\Models\Coupon::findByCode($o->coupon_code)?->decrement('used_count');
                    }
                    $o->cancelledBySystem('Online payment not completed in ' . self::PAYMENT_TIMEOUT_MINUTES . ' min')->update(['status' => 'cancelled', 'payment_status' => 'failed']);
                });
        } catch (\Throwable $e) {
            report($e);
        }
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    /**
     * Numbers only: date (YYMMDD) + that day's running number, e.g. 2611240012 = 24 Nov 2026, 12th order.
     * The last 4 digits (highlighted in admin) are easy to call out: "order 12". Older orders keep their old ORD-… numbers.
     */
    public static function generateOrderNumber(): string
    {
        $prefix = now()->format('ymd');
        $last = self::where('order_number', 'like', $prefix . '%')
            ->whereRaw('LENGTH(order_number) = ?', [strlen($prefix) + 4])
            ->max('order_number');
        $n = $last && ctype_digit((string) $last) ? ((int) substr((string) $last, -4)) + 1 : 1;
        for ($try = 0; $try < 50; $try++, $n++) {
            $number = $prefix . str_pad((string) $n, 4, '0', STR_PAD_LEFT);
            if (! self::where('order_number', $number)->exists()) {
                return $number;
            }
        }
        // extremely busy moment (two tills at the same second): fall back to a random 4-digit tail
        do {
            $number = $prefix . random_int(5000, 9999);
        } while (self::where('order_number', $number)->exists());
        return $number;
    }

    /** Create an order with the next number; if another till took that number in the same second, try the next one. */
    public static function createNumbered(array $data): self
    {
        for ($try = 0; ; $try++) {
            try {
                return self::create(['order_number' => self::generateOrderNumber()] + $data);
            } catch (\Illuminate\Database\UniqueConstraintViolationException $e) {
                if ($try >= 4) throw $e;
                usleep(random_int(20000, 120000));
            }
        }
    }

    public function getHighlightedOrderNumberHtmlAttribute(): string
    {
        $state = $this->order_number;
        $len = strlen($state);
        if ($len <= 4) {
            return "<span class='highlight-digits' style='background-color: #E6A817; color: #2C1A00; padding: 2px 6px; border-radius: 4px; font-weight: 800;'>{$state}</span>";
        }
        $main = substr($state, 0, -4);
        $lastFour = substr($state, -4);
        return htmlspecialchars($main) . "<span class='highlight-digits' style='background-color: #E6A817; color: #2C1A00; padding: 2px 6px; border-radius: 4px; font-weight: 800; box-shadow: 0 2px 6px rgba(230,168,23,0.3);'>{$lastFour}</span>";
    }

    public function getStatusLabelAttribute(): string
    {
        return match ($this->status) {
            'awaiting_payment' => 'Awaiting payment',
            'pending'          => 'Pending',
            'accepted'         => 'Accepted',
            'preparing'        => 'Preparing',
            'out_for_delivery' => 'Out for Delivery',
            'delivered'        => 'Delivered',
            'cancelled'        => 'Cancelled',
            default            => 'Unknown',
        };
    }
}
