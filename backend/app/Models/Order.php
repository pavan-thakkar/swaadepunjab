<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Order extends Model
{
    protected $fillable = [
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

    /** Secret part of the rating link (works on any phone, no login needed). */
    public function ratingToken(): string
    {
        return substr(hash_hmac('sha256', 'rate|' . $this->id . '|' . $this->order_number, (string) config('app.key')), 0, 24);
    }

    public function ratingUrl(): string
    {
        $site = rtrim((string) (config('app.frontend_url') ?: env('FRONTEND_URL', 'https://swaadepunjab.com')), '/');
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
                    $o->update(['status' => 'cancelled', 'payment_status' => 'failed']);
                });
        } catch (\Throwable $e) {
            report($e);
        }
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public static function generateOrderNumber(): string
    {
        do {
            // 6 random characters (no look-alike letters): about 1 billion combinations per day
            $chars = 'ABCDEFGHJKMNPQRSTUVWXYZ23456789';
            $rand = '';
            for ($i = 0; $i < 6; $i++) {
                $rand .= $chars[random_int(0, strlen($chars) - 1)];
            }
            $number = 'ORD-' . date('Ymd') . '-' . $rand;
        } while (self::where('order_number', $number)->exists());

        return $number;
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
