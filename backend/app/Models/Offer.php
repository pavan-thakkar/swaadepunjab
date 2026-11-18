<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Offer extends Model
{
    protected $fillable = [
        'title', 'description', 'min_order_amount', 'reward_type', 'free_menu_item_id',
        'free_quantity', 'discount_value', 'max_discount', 'starts_at', 'ends_at', 'is_active',
    ];

    protected $casts = [
        'min_order_amount' => 'decimal:2',
        'discount_value'   => 'decimal:2',
        'max_discount'     => 'decimal:2',
        'free_quantity'    => 'integer',
        'is_active'        => 'boolean',
        'starts_at'        => 'datetime',
        'ends_at'          => 'datetime',
    ];

    public function freeItem(): BelongsTo
    {
        return $this->belongsTo(MenuItem::class, 'free_menu_item_id');
    }

    /** Active right now, and (for free-item offers) the free dish is available. */
    public function scopeLive(Builder $q): Builder
    {
        return $q->where('is_active', true)
            ->where(fn ($w) => $w->whereNull('starts_at')->orWhere('starts_at', '<=', now()))
            ->where(fn ($w) => $w->whereNull('ends_at')->orWhere('ends_at', '>=', now()))
            ->where(fn ($w) => $w->where('reward_type', '!=', 'free_item')
                ->orWhereHas('freeItem', fn ($m) => $m->where('is_available', true)));
    }

    public function discountFor(float $subtotal): float
    {
        return match ($this->reward_type) {
            'flat_discount'    => min((float) $this->discount_value, $subtotal),
            'percent_discount' => round(min(
                $subtotal * ((float) $this->discount_value) / 100,
                $this->max_discount !== null ? (float) $this->max_discount : PHP_FLOAT_MAX
            ), 2),
            default => 0.0,
        };
    }

    /**
     * The offer an order gets: the qualifying offer with the highest minimum amount
     * (so bigger orders unlock bigger rewards). Only one offer per order.
     */
    public static function bestFor(float $subtotal): ?self
    {
        return static::live()->with('freeItem')
            ->where('min_order_amount', '<=', $subtotal)
            ->orderByDesc('min_order_amount')
            ->orderByDesc('id')
            ->first();
    }

    /** Line + totals to apply to an order. */
    public static function applyTo(float $subtotal): array
    {
        $offer = static::bestFor($subtotal);
        if (! $offer) {
            return ['offer' => null, 'discount' => 0.0, 'free_line' => null];
        }

        $freeLine = null;
        if ($offer->reward_type === 'free_item' && $offer->freeItem) {
            $freeLine = [
                'menu_item_id' => $offer->freeItem->id,
                'name'         => $offer->freeItem->name . ' (FREE 🎁)',
                'price'        => 0,
                'quantity'     => max(1, $offer->free_quantity),
                'subtotal'     => 0,
                'is_free'      => true,
            ];
        }

        return ['offer' => $offer, 'discount' => $offer->discountFor($subtotal), 'free_line' => $freeLine];
    }
}
