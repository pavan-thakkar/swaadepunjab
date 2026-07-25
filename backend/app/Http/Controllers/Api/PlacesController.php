<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Http;

class PlacesController extends Controller
{
    private string $apiKey = 'AIzaSyAPYRCJO5CNfxInjk-CHgmqI2J8ibhbCns';

    /**
     * Autocomplete address suggestions using Google Places API.
     */
    public function autocomplete(Request $request): JsonResponse
    {
        $query = $request->query('query', '');
        if (strlen(trim($query)) < 2) {
            return response()->json(['predictions' => []]);
        }

        $response = Http::get('https://maps.googleapis.com/maps/api/place/autocomplete/json', [
            'input'      => $query,
            'components' => 'country:in',
            'language'   => 'en',
            'key'        => $this->apiKey,
        ]);

        return response()->json($response->json());
    }

    /**
     * Geocode a place_id to get its lat/lng and address components.
     */
    public function geocode(Request $request): JsonResponse
    {
        $placeId = $request->query('place_id', '');
        if (!$placeId) {
            return response()->json(['status' => 'INVALID_REQUEST', 'results' => []]);
        }

        $response = Http::get('https://maps.googleapis.com/maps/api/geocode/json', [
            'place_id' => $placeId,
            'language' => 'en',
            'key'      => $this->apiKey,
        ]);

        return response()->json($response->json());
    }

    /**
     * Reverse geocode lat/lng to get a human-readable address.
     */
    public function reverse(Request $request): JsonResponse
    {
        $lat = $request->query('lat', '');
        $lng = $request->query('lng', '');
        if (!$lat || !$lng) {
            return response()->json(['status' => 'INVALID_REQUEST', 'results' => []]);
        }

        $response = Http::get('https://maps.googleapis.com/maps/api/geocode/json', [
            'latlng'   => "{$lat},{$lng}",
            'language' => 'en',
            'region'   => 'IN',
            'key'      => $this->apiKey,
        ]);

        return response()->json($response->json());
    }
}
