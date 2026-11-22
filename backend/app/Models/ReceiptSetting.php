<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ReceiptSetting extends Model
{
    protected $table = 'receipt_settings';

    protected $fillable = [
        'shop_upi_id',
        'shop_upi_name',
        'outlet_name',
        'outlet_address',
        'outlet_phone',
        'gst_number',
        'footer_message',
        'whatsapp_gateway_url',
        'whatsapp_gateway_image_url',
        'whatsapp_gateway_token',
        'whatsapp_gateway_payload_template',
        'google_maps_api_key',
        'delivery_base_km',
        'delivery_base_fee',
        'delivery_extra_fee_per_km',
        'delivery_max_distance',
        'delivery_fallback_fee',
        'order_edit_minutes',
        'delivery_enabled',
        'delivery_hours_enabled',
        'delivery_start_time',
        'delivery_end_time',
        'delivery_off_message',
        'loyalty_enabled',
        'loyalty_earn_per_100',
        'loyalty_point_value',
        'loyalty_min_redeem',
        'loyalty_max_redeem_percent',
        'razorpay_enabled',
        'razorpay_key_id',
        'razorpay_key_secret',
        'razorpay_webhook_secret',
        'gst_enabled',
        'gst_rate',
        'gst_mode',
    ];

    // Payment secrets are encrypted in the database and never sent anywhere
    protected $hidden = ['razorpay_key_secret', 'razorpay_webhook_secret'];

    protected $casts = [
        'razorpay_enabled'        => 'boolean',
        'gst_enabled'             => 'boolean',
        'gst_rate'                => 'float',
        'razorpay_key_secret'     => 'encrypted',
        'razorpay_webhook_secret' => 'encrypted',
    ];

    /**
     * Helper to get the single configuration settings instance.
     */
    public static function getSettings(): self
    {
        $settings = self::first();
        if (! $settings) {
            $settings = self::create([
                'outlet_name'    => 'SWAD E PUNJAB',
                'outlet_address' => '123, Food Street, Near Circle',
                'outlet_phone'   => '+91 98765 43210',
                'gst_number'     => null,
                'footer_message' => 'Swad E Punjab Taste of Tradition',
                'delivery_max_distance' => 20,
            ]);
        }
        if ($settings->delivery_max_distance != 20) {
            $settings->update(['delivery_max_distance' => 20]);
        }
        return $settings;
    }

    /**
     * Is home delivery available right now? (Admin → Store & Receipt Config)
     * Supports overnight hours, e.g. 6:00 PM → 2:00 AM.
     */
    public static function deliveryStatus(): array
    {
        $s = static::getSettings();
        $fmt = fn ($t) => $t ? \Carbon\Carbon::createFromFormat('H:i:s', strlen($t) === 5 ? $t . ':00' : $t)->format('h:i A') : null;
        $start = $s->delivery_start_time;
        $end = $s->delivery_end_time;
        $hoursText = ($s->delivery_hours_enabled && $start && $end) ? $fmt($start) . ' – ' . $fmt($end) : null;

        $available = (bool) ($s->delivery_enabled ?? true);
        $reason = null;

        if (! $available) {
            $reason = 'off';
        } elseif ($hoursText) {
            $now = now()->format('H:i:s');
            $startN = strlen($start) === 5 ? $start . ':00' : $start;
            $endN = strlen($end) === 5 ? $end . ':00' : $end;
            $inside = $startN <= $endN
                ? ($now >= $startN && $now <= $endN)
                : ($now >= $startN || $now <= $endN); // overnight
            if (! $inside) {
                $available = false;
                $reason = 'outside_hours';
            }
        }

        $message = null;
        if (! $available) {
            $message = $s->delivery_off_message ?: ($reason === 'outside_hours'
                ? "Delivery is available only {$hoursText}. You can still order Take Away or Dine In."
                : 'Home delivery is not available right now. You can still order Take Away or Dine In.');
        }

        return [
            'available' => $available,
            'reason'    => $reason,
            'hours'     => $hoursText,
            'message'   => $message,
        ];
    }
}
