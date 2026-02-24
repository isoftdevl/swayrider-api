<?php

namespace Database\Seeders;

use App\Models\Dispute;
use App\Models\Delivery;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class DisputeSeeder extends Seeder
{
    public function run(): void
    {
        $deliveries = Delivery::all();

        if ($deliveries->isEmpty()) {
            return;
        }

        // Create 5 disputes
        for ($i = 0; $i < 5; $i++) {
            $delivery = $deliveries->random();
            $isUser = rand(0, 1);
            $raiser = $isUser ? $delivery->user : $delivery->rider;
            
            if (!$raiser) continue;

            Dispute::create([
                'delivery_id' => $delivery->id,
                'raised_by_type' => get_class($raiser),
                'raised_by_id' => $raiser->id,
                'status' => collect(['open', 'investigating', 'resolved', 'closed'])->random(),
                'subject' => 'Item damaged',
                'description' => 'The item was damaged during transit.',
                'resolution' => null,
                'resolved_at' => null,
                'resolved_by' => null,
            ]);
        }
    }
}
