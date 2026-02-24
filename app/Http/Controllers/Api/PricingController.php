<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\MapboxService;
use App\Services\OSRMService;
use App\Services\PricingService;
use Illuminate\Http\Request;

class PricingController extends Controller
{
    protected $pricingService;
    protected $mapboxService;
    protected $osrmService;

    public function __construct(PricingService $pricingService, MapboxService $mapboxService, OSRMService $osrmService)
    {
        $this->pricingService = $pricingService;
        $this->mapboxService = $mapboxService;
        $this->osrmService = $osrmService;
    }

    public function estimate(Request $request)
    {
        $request->validate([
            'pickup_latitude' => 'required|numeric',
            'pickup_longitude' => 'required|numeric',
            'dropoff_latitude' => 'required|numeric',
            'dropoff_longitude' => 'required|numeric',
            'package_size' => 'required|in:small,medium,large',
            'urgency' => 'in:normal,express'
        ]);

        // 1. Get Distance (and Duration) - Try Mapbox (with OSRM fallback inside service)
        $routeData = $this->mapboxService->getDistanceAndDuration(
            $request->pickup_latitude, $request->pickup_longitude,
            $request->dropoff_latitude, $request->dropoff_longitude
        );

        // 2. Calculate Price
        $estimate = $this->pricingService->calculateDeliveryPrice(
            $routeData['distance_km'],
            $request->package_size,
            $request->urgency ?? 'normal'
        );

        return response()->json([
            'success' => true,
            'data' => array_merge($estimate, [
                'estimated_duration_min' => $routeData['duration_min']
            ])
        ]);
    }
}
