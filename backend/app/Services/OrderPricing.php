<?php

namespace App\Services;

use App\Models\Coupon;
use App\Models\Offer;

/**
 * One place that decides offers + coupon for an order subtotal.
 */
class OrderPricing
{
    /**
     * @return array{offer: ?Offer, offer_discount: float, free_line: ?array, coupon: ?Coupon, coupon_discount: float, error: ?string}
     */
    public static function calculate(float $subtotal, ?string $couponCode, ?string $phone, ?int $ignoreOrderId = null, bool $strictCoupon = true): array
    {
        $coupon = null;
        $couponDiscount = 0.0;
        $error = null;

        if ($couponCode !== null && trim($couponCode) !== '') {
            $found = Coupon::findByCode($couponCode);
            if (! $found) {
                $error = 'Coupon code not found.';
            } else {
                $res = $found->check($subtotal, $phone, $ignoreOrderId);
                if ($res['ok']) {
                    $coupon = $found;
                } else {
                    $error = $res['message'];
                }
            }
            if ($error && $strictCoupon) {
                return ['offer' => null, 'offer_discount' => 0.0, 'free_line' => null, 'coupon' => null, 'coupon_discount' => 0.0, 'error' => $error];
            }
        }

        // Automatic offer (skipped when the coupon can't be combined with offers)
        $applied = ['offer' => null, 'discount' => 0.0, 'free_line' => null];
        if (! $coupon || $coupon->combine_with_offers) {
            $applied = Offer::applyTo($subtotal);
        }

        if ($coupon) {
            $couponDiscount = $coupon->discountFor(max(0, $subtotal - $applied['discount']));
        }

        return [
            'offer'           => $applied['offer'],
            'offer_discount'  => (float) $applied['discount'],
            'free_line'       => $applied['free_line'],
            'coupon'          => $coupon,
            'coupon_discount' => $couponDiscount,
            'error'           => $error,
        ];
    }
}
