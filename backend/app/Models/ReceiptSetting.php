<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ReceiptSetting extends Model
{
    protected $table = 'receipt_settings';

    protected $fillable = [
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
}
