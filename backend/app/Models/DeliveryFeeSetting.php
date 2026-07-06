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

    /**
     * Calculate delivery fee based on Google Maps distance between outlet and destination.
     * Returns an array: ['distance_km' => float, 'fee' => float, 'error' => ?string]
     */
    public static function calculateDistanceAndFee(string $deliveryAddress, string $city, float $subtotal = 0, ?float $latitude = null, ?float $longitude = null): array
    {
        $settings = \App\Models\ReceiptSetting::getSettings();
        
        $apiKey = $settings->google_maps_api_key;
        $outletAddress = $settings->outlet_address;

        $fallbackFee = (float) $settings->delivery_fallback_fee;
        if ($settings->delivery_max_distance != 20) {
            $settings->update(['delivery_max_distance' => 20]);
        }
        $maxDistance = 20.0;

        // If no API key is set, calculate Haversine distance using coordinates if provided, otherwise simulate 3.5 KM
        if (empty($apiKey)) {
            if ($latitude !== null && $longitude !== null) {
                $earthRadius = 6371; // in KM
                $lat1 = 23.1854; // Outlet latitude (Mapple 99 Food County)
                $lon1 = 72.6395; // Outlet longitude
                
                $dLat = deg2rad($latitude - $lat1);
                $dLon = deg2rad($longitude - $lon1);
                
                $a = sin($dLat/2) * sin($dLat/2) +
                     cos(deg2rad($lat1)) * cos(deg2rad($latitude)) *
                     sin($dLon/2) * sin($dLon/2);
                
                $c = 2 * atan2(sqrt($a), sqrt(1-$a));
                $distanceKm = $earthRadius * $c;
                
                if ($distanceKm > $maxDistance) {
                    return [
                        'distance_km' => round($distanceKm, 2),
                        'fee'         => 0,
                        'error'       => "Delivery address is too far (" . round($distanceKm, 2) . " KM). Maximum delivery radius is 20 KM."
                    ];
                }
                
                $fee = self::getFeeForDistance($distanceKm, $subtotal) ?? $fallbackFee;
                return [
                    'distance_km' => round($distanceKm, 2),
                    'fee'         => round($fee, 2),
                    'error'       => null
                ];
            }

            // Simulated distance fallback
            $simulatedDistance = 3.5; 
            $fee = self::getFeeForDistance($simulatedDistance, $subtotal) ?? $fallbackFee;
            
            \Illuminate\Support\Facades\Log::warning("Google Maps API Key not set. Simulating distance {$simulatedDistance} KM. Fee: ₹{$fee}");
            return [
                'distance_km' => $simulatedDistance,
                'fee'         => $fee,
                'error'       => null
            ];
        }

        $origin = urlencode($outletAddress);
        $destination = urlencode($deliveryAddress . ', ' . $city);

        try {
            $client = new \GuzzleHttp\Client();
            $url = "https://maps.googleapis.com/maps/api/distancematrix/json?origins={$origin}&destinations={$destination}&key={$apiKey}";
            
            $response = $client->get($url, ['timeout' => 5]);
            $body = json_decode($response->getBody(), true);

            if (isset($body['rows'][0]['elements'][0]['distance']['value'])) {
                $distanceMeters = $body['rows'][0]['elements'][0]['distance']['value'];
                $distanceKm = $distanceMeters / 1000;

                if ($distanceKm > $maxDistance) {
                    return [
                        'distance_km' => round($distanceKm, 2),
                        'fee'         => 0,
                        'error'       => "Delivery address is too far (" . round($distanceKm, 2) . " KM). Maximum delivery radius is 20 KM."
                    ];
                }

                // Look up fee in distance tiers table
                $fee = self::getFeeForDistance($distanceKm, $subtotal);
                
                if (is_null($fee)) {
                    $fee = $fallbackFee;
                }

                return [
                    'distance_km' => round($distanceKm, 2),
                    'fee'         => round($fee, 2),
                    'error'       => null
                ];
            } else {
                $status = $body['rows'][0]['elements'][0]['status'] ?? ($body['status'] ?? 'UNKNOWN_ERROR');
                \Illuminate\Support\Facades\Log::error("Google Distance Matrix API Error: Status = " . $status);
                return [
                    'distance_km' => null,
                    'fee'         => $fallbackFee,
                    'error'       => 'Could not calculate distance: ' . $status
                ];
            }
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error("Google Distance Matrix API Exception: " . $e->getMessage());
            return [
                'distance_km' => null,
                'fee'         => $fallbackFee,
                'error'       => 'API Request Failed: ' . $e->getMessage()
            ];
        }
    }
}
