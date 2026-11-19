<?php

namespace App\Services;

use App\Models\LoyaltyTransaction;
use App\Models\Order;
use App\Models\ReceiptSetting;
use App\Support\CustomerToken;

/**
 * Loyalty points: earned when an order is delivered, used as a discount at checkout.
 * Admin turns it on/off and sets the rates in Store & Receipt Config.
 */
class Loyalty
{
    public static function settings(): array
    {
        $s = ReceiptSetting::getSettings();
        return [
            'enabled'            => (bool) ($s->loyalty_enabled ?? false),
            'earn_per_100'       => (float) ($s->loyalty_earn_per_100 ?? 5),
            'point_value'        => (float) ($s->loyalty_point_value ?? 1),
            'min_redeem'         => (int) ($s->loyalty_min_redeem ?? 50),
            'max_redeem_percent' => (int) ($s->loyalty_max_redeem_percent ?? 50),
        ];
    }

    public static function balance(?string $phone): int
    {
        $p = CustomerToken::last10($phone);
        return $p ? (int) LoyaltyTransaction::where('phone', $p)->sum('points') : 0;
    }

    /** Amount (after offers/coupons) that earns points and can be paid with points. */
    public static function eligibleAmount(Order $order): float
    {
        return max(0, (float) $order->subtotal - (float) $order->discount - (float) $order->coupon_discount);
    }

    public static function pointsToEarn(float $amount): int
    {
        $s = self::settings();
        return $s['enabled'] ? (int) floor($amount / 100 * $s['earn_per_100']) : 0;
    }

    /**
     * How many points can actually be used on an order of $amount (after other discounts).
     * @return array{points: int, discount: float}
     */
    public static function redeemable(int $wanted, int $balance, float $amount): array
    {
        $s = self::settings();
        if (! $s['enabled'] || $wanted <= 0 || $s['point_value'] <= 0) {
            return ['points' => 0, 'discount' => 0.0];
        }
        $maxByOrder = (int) floor(($amount * $s['max_redeem_percent'] / 100) / $s['point_value']);
        $points = min($wanted, $balance, $maxByOrder);
        if ($points < $s['min_redeem']) {
            return ['points' => 0, 'discount' => 0.0];
        }
        return ['points' => $points, 'discount' => round($points * $s['point_value'], 2)];
    }

    /** Called whenever an order's status changes. */
    public static function onStatusChanged(Order $order): void
    {
        $phone = CustomerToken::last10($order->customer_phone);
        if (! $phone) {
            return;
        }

        if ($order->status === 'delivered') {
            $already = LoyaltyTransaction::where('order_id', $order->id)->where('type', 'earn')->exists();
            $reversed = LoyaltyTransaction::where('order_id', $order->id)->where('type', 'reverse')->exists();
            $points = self::pointsToEarn(max(0, self::eligibleAmount($order) - (float) $order->points_discount));
            if (! $already && ! $reversed && $points > 0) {
                LoyaltyTransaction::create([
                    'phone' => $phone, 'order_id' => $order->id, 'points' => $points, 'type' => 'earn',
                    'note' => "Earned on order {$order->order_number}",
                ]);
            }
        }

        if ($order->status === 'cancelled') {
            // Give back points the customer used
            if ($order->points_redeemed > 0 && ! LoyaltyTransaction::where('order_id', $order->id)->where('type', 'refund')->exists()) {
                LoyaltyTransaction::create([
                    'phone' => $phone, 'order_id' => $order->id, 'points' => (int) $order->points_redeemed, 'type' => 'refund',
                    'note' => "Returned: order {$order->order_number} cancelled",
                ]);
            }
            // Take back points earned on it (if it was marked delivered earlier)
            $earned = LoyaltyTransaction::where('order_id', $order->id)->where('type', 'earn')->sum('points');
            if ($earned > 0 && ! LoyaltyTransaction::where('order_id', $order->id)->where('type', 'reverse')->exists()) {
                LoyaltyTransaction::create([
                    'phone' => $phone, 'order_id' => $order->id, 'points' => -$earned, 'type' => 'reverse',
                    'note' => "Removed: order {$order->order_number} cancelled",
                ]);
            }
        }
    }
}
