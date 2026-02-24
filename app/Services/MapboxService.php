<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class MapboxService
{
    protected $accessToken;
    protected $osrmService;

    public function __construct(OSRMService $osrmService)
    {
        $this->accessToken = env('MAPBOX_ACCESS_TOKEN');
        $this->osrmService = $osrmService;
    }

    /**
     * Get Address Geocoding
     */
    public function geocode($query)
    {
        if (empty($this->accessToken)) {
            Log::error("Mapbox Access Token is missing");
            return null;
        }

        $response = Http::get("https://api.mapbox.com/geocoding/v5/mapbox.places/" . urlencode($query) . ".json", [
            'access_token' => $this->accessToken,
            'limit' => 5,
            'country' => 'NG', // Restrict to Nigeria for SwayRider
        ]);

        if ($response->successful()) {
            return $response->json();
        }

        return null;
    }

    /**
     * Get Distance and Duration using Mapbox Directions
     * Falls back to OSRM if Mapbox fails or token is missing
     */
    public function getDistanceAndDuration($lat1, $lng1, $lat2, $lng2)
    {
        if (empty($this->accessToken)) {
            Log::warning("Mapbox token missing, falling back to OSRM");
            return $this->osrmService->getDistanceAndDuration($lat1, $lng1, $lat2, $lng2);
        }

        $coords = "{$lng1},{$lat1};{$lng2},{$lat2}";
        $url = "https://api.mapbox.com/directions/v5/mapbox/driving/{$coords}";

        $response = Http::get($url, [
            'access_token' => $this->accessToken,
            'overview' => 'full',
            'geometries' => 'geojson',
        ]);

        if ($response->successful()) {
            $data = $response->json();
            if (isset($data['routes'][0])) {
                $route = $data['routes'][0];
                return [
                    'distance_km' => $route['distance'] / 1000,
                    'duration_min' => ceil($route['duration'] / 60),
                    'geometry' => $route['geometry'],
                    'source' => 'Mapbox'
                ];
            }
        }

        Log::error("Mapbox Directions API failed, falling back to OSRM");
        return $this->osrmService->getDistanceAndDuration($lat1, $lng1, $lat2, $lng2);
    }
}
