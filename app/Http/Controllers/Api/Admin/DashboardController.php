<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Delivery;
use App\Models\Rider;
use App\Models\User;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function stats()
    {
        return response()->json([
            'success' => true,
            'data' => [
                'total_users' => User::count(),
                'total_riders' => Rider::count(),
                'active_deliveries' => Delivery::whereIn('status', ['assigned', 'picked_up', 'in_transit'])->count(),
                'pending_kyc' => Rider::where('status', 'pending')->count(),
                // Add revenue stats...
            ]
        ]);
    }
}
