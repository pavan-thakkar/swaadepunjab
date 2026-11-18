<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Order extends Model
{
    protected $fillable = [
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
    ];

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
        return ! in_array($this->payment_method, ['cash_on_delivery', null, ''], true);
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public static function generateOrderNumber(): string
    {
        do {
            $number = 'ORD-' . date('Ymd') . '-' . rand(1000, 9999);
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
