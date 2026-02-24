<?php

namespace Database\Factories;

use App\Models\RiderProfile;
use Illuminate\Database\Eloquent\Factories\Factory;

class RiderProfileFactory extends Factory
{
    protected $model = RiderProfile::class;

    public function definition(): array
    {
        return [
            // Identification - EXACT values from migration
            'id_type' => $this->faker->randomElement([
                'national_id',
                'drivers_license',
                'voters_card',
                'international_passport',
            ]),
            'id_number' => 'NIN-' . $this->faker->numerify('##########'),
            'id_front_photo' => 'documents/id_front_sample.jpg',
            'id_back_photo' => 'documents/id_back_sample.jpg',
            'selfie_photo' => 'documents/selfie_sample.jpg',

            // Bike
            'bike_registration_number' => strtoupper(
                $this->faker->bothify('???-###-??')
            ),
            'bike_photo' => 'documents/bike_photo.jpg',
            'bike_papers' => 'documents/bike_papers.pdf',

            // Security
            'police_clearance' => 'documents/police_clearance.pdf',

            // Emergency contact
            'emergency_contact_name' => $this->faker->name(),
            'emergency_contact_phone' => $this->faker->numerify('080########'),

            // Address
            'address' => $this->faker->streetAddress(),
            'city' => $this->faker->city(),
            'state' => $this->faker->state(),

            // Verification - EXACT values from migration
            'verification_status' => $this->faker->randomElement([
                'pending',
                'approved',
                'rejected',
            ]),

            // Admin verification (nullable)
            'rejection_reason' => null,
            'verified_by' => null,
            'verified_at' => null,
        ];
    }
}