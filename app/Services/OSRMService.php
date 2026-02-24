<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class OSRMService 
{
    protected $baseUrl;

    public function __construct()
    {
        // Default to public OSRM profile for demo, but plan allows for self-hosted URL in .env
        $this->baseUrl = env('OSRM_BASE_URL', 'https://router.project-osrm.org');
    }

    /**
     * Get Distance (km) and Duration (min) between two points
     */
    public function getDistanceAndDuration($pickupLat, $pickupLng, $dropoffLat, $dropoffLng)
    {
        try {
            $url = "{$this->baseUrl}/route/v1/driving/{$pickupLng},{$pickupLat};{$dropoffLng},{$dropoffLat}?overview=false";
            
            $response = Http::timeout(5)->get($url);

            if ($response->successful()) {
                $data = $response->json();
                if (isset($data['routes'][0])) {
                    $route = $data['routes'][0];
                    return [
                        'distance_km' => $route['distance'] / 1000,
                        'duration_min' => ceil($route['duration'] / 60),
                        'source' => 'OSRM'
                    ];
                }
            }
            
            Log::warning("OSRM Route request failed or returned no routes.", ['response' => $response->body()]);
        } catch (\Exception $e) {
            Log::error("OSRM Service Error: " . $e->getMessage());
        }

        return null;
    }
}
