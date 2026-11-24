<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DeliveryFeeSetting extends Model
{
    protected $table = 'delivery_fee_settings';

    protected $fillable = [
        'min_distance_km',
        'max_distance_km',
        'min_order_amount',
        'delivery_fee',
    ];

    protected $casts = [
        'min_distance_km' => 'float',
        'max_distance_km' => 'float',
        'min_order_amount' => 'float',
        'delivery_fee'     => 'float',
    ];

    /**
     * Helper to find matching tier based on distance KM and order subtotal.
     */
    public static function getFeeForDistance(float $distanceKm, float $subtotal): ?float
    {
        $tier = self::where('min_distance_km', '<=', $distanceKm)
            ->where(function ($query) use ($distanceKm) {
                $query->where('max_distance_km', '>=', $distanceKm)
                    ->orWhereNull('max_distance_km');
            })
            ->where('min_order_amount', '<=', $subtotal)
            ->orderBy('delivery_fee', 'asc') // Pick the cheapest valid fee matching conditions
            ->first();

        return $tier ? (float) $tier->delivery_fee : null;
    }

    /** Old fixed shop point, used until the owner sets the shop location in admin. */
    public const DEFAULT_SHOP_LAT = 23.1648274;
    public const DEFAULT_SHOP_LNG = 72.6826934;

    /** Shop location + delivery radius from Store & Receipt Config. */
    public static function area(): array
    {
        $s = \App\Models\ReceiptSetting::getSettings();
        $lat = (float) ($s->outlet_latitude ?: self::DEFAULT_SHOP_LAT);
        $lng = (float) ($s->outlet_longitude ?: self::DEFAULT_SHOP_LNG);
        $max = (float) ($s->delivery_max_distance ?: 20);
        return ['lat' => $lat, 'lng' => $lng, 'max_km' => max(0.5, $max)];
    }

    /** Straight-line ("as the crow flies") distance in km between two GPS points. */
    public static function straightKm(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $r = 6371;
        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);
        $a = sin($dLat / 2) ** 2 + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng / 2) ** 2;
        return $r * 2 * atan2(sqrt($a), sqrt(1 - $a));
    }

    /**
     * Delivery distance + fee for an address.
     * Uses Google road distance when a working Google key is saved; otherwise the straight-line distance from the
     * customer's map pin. Addresses beyond the admin's delivery radius get a "too far" error (the order is refused).
     * Returns ['distance_km' => ?float, 'fee' => float, 'error' => ?string]
     */
    public static function calculateDistanceAndFee(string $deliveryAddress, string $city, float $subtotal = 0, ?float $latitude = null, ?float $longitude = null): array
    {
        $settings = \App\Models\ReceiptSetting::getSettings();
        $area = self::area();
        $maxDistance = $area['max_km'];
        $fallbackFee = (float) $settings->delivery_fallback_fee;
        $hasPin = $latitude !== null && $longitude !== null && ($latitude != 0 || $longitude != 0);

        $finish = function (float $km) use ($maxDistance, $subtotal, $fallbackFee): array {
            if ($km > $maxDistance) {
                $max = rtrim(rtrim(number_format($maxDistance, 1), '0'), '.');
                return [
                    'distance_km' => round($km, 2),
                    'fee'         => 0,
                    'error'       => "Sorry, this address is too far — " . round($km, 1) . " km away. We deliver only within {$max} km of our restaurant. You can choose Take Away instead.",
                ];
            }
            $fee = self::getFeeForDistance($km, $subtotal) ?? $fallbackFee;
            return ['distance_km' => round($km, 2), 'fee' => round($fee, 2), 'error' => null];
        };

        // 1) Google road distance (only if a key is saved and Google answers)
        $apiKey = $settings->google_maps_api_key;
        if (! empty($apiKey)) {
            try {
                $origin = $area['lat'] . ',' . $area['lng'];
                $destination = $hasPin ? ($latitude . ',' . $longitude) : ($deliveryAddress . ', ' . $city);
                $res = \Illuminate\Support\Facades\Http::timeout(5)->get('https://maps.googleapis.com/maps/api/distancematrix/json', [
                    'origins' => $origin, 'destinations' => $destination, 'key' => $apiKey,
                ]);
                $meters = $res->json('rows.0.elements.0.distance.value');
                if ($meters !== null) {
                    return $finish($meters / 1000);
                }
                \Illuminate\Support\Facades\Log::warning('Google Distance Matrix gave no distance: ' . ($res->json('rows.0.elements.0.status') ?? $res->json('status') ?? 'unknown') . ' — using straight-line distance');
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::warning('Google Distance Matrix failed: ' . $e->getMessage() . ' — using straight-line distance');
            }
        }

        // 2) Straight-line distance from the customer's map pin
        if ($hasPin) {
            return $finish(self::straightKm($area['lat'], $area['lng'], (float) $latitude, (float) $longitude));
        }

        // 3) No pin and no Google: we cannot measure — charge the fallback fee, don't block the customer
        return ['distance_km' => null, 'fee' => $fallbackFee, 'error' => null];
    }
}
