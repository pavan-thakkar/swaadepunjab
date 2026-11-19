<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Coupon extends Model
{
    protected $fillable = [
        'code', 'description', 'discount_type', 'discount_value', 'max_discount', 'min_order_amount',
        'usage_limit', 'per_customer_limit', 'used_count', 'combine_with_offers', 'starts_at', 'ends_at', 'is_active',
    ];

    protected $casts = [
        'discount_value'      => 'decimal:2',
        'max_discount'        => 'decimal:2',
        'min_order_amount'    => 'decimal:2',
        'usage_limit'         => 'integer',
        'per_customer_limit'  => 'integer',
        'used_count'          => 'integer',
        'combine_with_offers' => 'boolean',
        'is_active'           => 'boolean',
        'starts_at'           => 'datetime',
        'ends_at'             => 'datetime',
    ];

    public function setCodeAttribute($value): void
    {
        $this->attributes['code'] = strtoupper(trim((string) $value));
    }

    public static function findByCode(?string $code): ?self
    {
        $code = strtoupper(trim((string) $code));
        return $code === '' ? null : static::where('code', $code)->first();
    }

    public function discountFor(float $amount): float
    {
        $d = $this->discount_type === 'percent'
            ? $amount * ((float) $this->discount_value) / 100
            : (float) $this->discount_value;
        if ($this->discount_type === 'percent' && $this->max_discount !== null) {
            $d = min($d, (float) $this->max_discount);
        }
        return round(max(0, min($d, $amount)), 2);
    }

    /**
     * Check the coupon for an order. $ignoreOrderId: when re-checking an existing order.
     * Returns ['ok' => bool, 'message' => string].
     */
    public function check(float $subtotal, ?string $phone = null, ?int $ignoreOrderId = null): array
    {
        if (! $this->is_active) {
            return ['ok' => false, 'message' => 'This coupon is not active.'];
        }
        if ($this->starts_at && now()->lt($this->starts_at)) {
            return ['ok' => false, 'message' => 'This coupon is not active yet.'];
        }
        if ($this->ends_at && now()->gt($this->ends_at)) {
            return ['ok' => false, 'message' => 'This coupon has expired.'];
        }
        if ($subtotal < (float) $this->min_order_amount) {
            $more = (int) ceil((float) $this->min_order_amount - $subtotal);
            return ['ok' => false, 'message' => "Add ₹{$more} more to use {$this->code} (min. order ₹" . number_format((float) $this->min_order_amount, 0) . ').'];
        }
        if (! $ignoreOrderId && $this->usage_limit !== null && $this->used_count >= $this->usage_limit) {
            return ['ok' => false, 'message' => 'This coupon has been fully used.'];
        }
        if ($phone && $this->per_customer_limit) {
            $digits = preg_replace('/\D/', '', $phone);
            $last10 = substr($digits, -10);
            $used = Order::where('coupon_code', $this->code)
                ->whereNotIn('status', ['cancelled', 'awaiting_payment'])
                ->when($ignoreOrderId, fn ($q) => $q->where('id', '!=', $ignoreOrderId))
                ->where('customer_phone', 'like', '%' . $last10)
                ->count();
            if ($used >= $this->per_customer_limit) {
                return ['ok' => false, 'message' => 'You have already used this coupon.'];
            }
        }
        return ['ok' => true, 'message' => 'Coupon applied!'];
    }
}
