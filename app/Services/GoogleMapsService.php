<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

class GoogleMapsService
{
    protected $apiKey;

    public function __construct()
    {
        $this->apiKey = env('GOOGLE_MAPS_API_KEY'); // Ensure this is set in .env
    }

    public function getDistanceAndDuration($pickupLat, $pickupLng, $dropoffLat, $dropoffLng)
    {
        $response = Http::get('https://maps.googleapis.com/maps/api/distancematrix/json', [
            'origins' => "$pickupLat,$pickupLng",
            'destinations' => "$dropoffLat,$dropoffLng",
            'key' => $this->apiKey,
        ]);

        $data = $response->json();

        if ($data['status'] === 'OK' && isset($data['rows'][0]['elements'][0])) {
            $element = $data['rows'][0]['elements'][0];
            if ($element['status'] === 'OK') {
                return [
                    'distance_km' => $element['distance']['value'] / 1000,
                    'duration_min' => ceil($element['duration']['value'] / 60)
                ];
            }
        }
        
        // Fallback
        return [
            'distance_km' => $this->haversine($pickupLat, $pickupLng, $dropoffLat, $dropoffLng),
            'duration_min' => 30 // Dummy fallback
        ];
    }
    
    private function haversine($latitudeFrom, $longitudeFrom, $latitudeTo, $longitudeTo)
    {
        $radius = 6371;
        $latFrom = deg2rad($latitudeFrom);
        $lonFrom = deg2rad($longitudeFrom);
        $latTo = deg2rad($latitudeTo);
        $lonTo = deg2rad($longitudeTo);

        $latDelta = $latTo - $latFrom;
        $lonDelta = $lonTo - $lonFrom;

        $angle = 2 * asin(sqrt(pow(sin($latDelta / 2), 2) +
        cos($latFrom) * cos($latTo) * pow(sin($lonDelta / 2), 2)));
        
        return $angle * $radius;
    }
}
