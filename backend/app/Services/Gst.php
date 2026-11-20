<?php

namespace App\Services;

use App\Models\Order;
use App\Models\ReceiptSetting;

/**
 * GST on the food bill (Admin → Store & Receipt Config).
 *
 *  - exclusive: GST is added on top of the food amount (₹200 + 5% = ₹210)
 *  - inclusive: prices already include GST; the bill only shows the split (₹200 = ₹190.48 + ₹9.52)
 *
 * GST is worked out on the food amount after discounts, coupon and points.
 * The delivery fee is not taxed here.
 */
class Gst
{
    public const MODES = [
        'exclusive' => 'Add GST on top of the price',
        'inclusive' => 'Price already includes GST',
    ];

    private static ?array $cache = null;

    /** @return array{enabled: bool, rate: float, mode: string} */
    public static function settings(): array
    {
        if (self::$cache !== null) {
            return self::$cache;
        }
        try {
            $s = ReceiptSetting::first();
        } catch (\Throwable $e) {
            $s = null;
        }
        $rate = (float) ($s->gst_rate ?? 5);
        $mode = in_array($s->gst_mode ?? null, ['exclusive', 'inclusive'], true) ? $s->gst_mode : 'exclusive';

        return self::$cache = [
            'enabled' => (bool) ($s->gst_enabled ?? false) && $rate > 0,
            'rate'    => $rate,
            'mode'    => $mode,
        ];
    }

    public static function flush(): void
    {
        self::$cache = null;
    }

    /**
     * @return array{gst_amount: float, gst_rate: ?float, gst_mode: ?string, add: float}
     *         add = amount to add to the order total (0 when GST is inclusive or off)
     */
    public static function calculate(float $taxable, ?float $rate = null, ?string $mode = null): array
    {
        if ($rate === null) {
            $s = self::settings();
            if (! $s['enabled']) {
                return self::none();
            }
            $rate = $s['rate'];
            $mode = $s['mode'];
        }
        if ($rate <= 0) {
            return self::none();
        }
        $mode = $mode === 'inclusive' ? 'inclusive' : 'exclusive';
        $taxable = max(0, $taxable);

        $amount = $mode === 'inclusive'
            ? $taxable - ($taxable / (1 + $rate / 100))
            : $taxable * $rate / 100;
        $amount = round($amount, 2);

        return [
            'gst_amount' => $amount,
            'gst_rate'   => $rate,
            'gst_mode'   => $mode,
            'add'        => $mode === 'exclusive' ? $amount : 0.0,
        ];
    }

    /** Same rate/mode the order was placed with (used when an order is changed later). */
    public static function forExistingOrder(Order $order, float $taxable): array
    {
        if ($order->gst_rate === null || (float) $order->gst_rate <= 0) {
            return self::none();
        }

        return self::calculate($taxable, (float) $order->gst_rate, $order->gst_mode);
    }

    private static function none(): array
    {
        return ['gst_amount' => 0.0, 'gst_rate' => null, 'gst_mode' => null, 'add' => 0.0];
    }

    /** Lines for a bill: [label, amount] for CGST + SGST (half each). */
    public static function billLines(Order $order): array
    {
        $amt = (float) ($order->gst_amount ?? 0);
        if ($amt <= 0 || ! $order->gst_rate) {
            return [];
        }
        $half = round($amt / 2, 2);
        $halfRate = rtrim(rtrim(number_format((float) $order->gst_rate / 2, 2), '0'), '.');

        return [
            ["CGST @ {$halfRate}%", $half],
            ["SGST @ {$halfRate}%", round($amt - $half, 2)],
        ];
    }

    public static function publicArray(): array
    {
        $s = self::settings();

        return ['enabled' => $s['enabled'], 'rate' => $s['rate'], 'mode' => $s['mode']];
    }
}
