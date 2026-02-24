<?php

namespace Database\Factories;

use App\Models\Rider;
use App\Models\Company;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class RiderFactory extends Factory
{
    protected $model = Rider::class;

    public function definition(): array
    {
        return [
            'name' => $this->faker->name(),
            'email' => $this->faker->unique()->safeEmail(),
            'phone' => '080' . $this->faker->numerify('########'),
            'password' => Hash::make('password'),
            'profile_photo' => null,
            'status' => $this->faker->randomElement(['pending', 'approved', 'active', 'suspended', 'banned', 'rejected']),
            'is_online' => $this->faker->boolean(30), // 30% chance of being online
            
            // Lagos coordinates with some randomization
            'current_latitude' => $this->faker->randomFloat(6, 6.4000, 6.6000),
            'current_longitude' => $this->faker->randomFloat(6, 3.3000, 3.5000),
            
            'rating' => $this->faker->randomFloat(2, 3.0, 5.0),
            'total_deliveries' => $this->faker->numberBetween(0, 500),
            'total_earnings' => $this->faker->randomFloat(2, 0, 1000000),
            
            'bike_registration_number' => strtoupper($this->faker->bothify('???-###-??')),
            
            'emergency_contact_name' => $this->faker->name(),
            'emergency_contact_phone' => '080' . $this->faker->numerify('########'),
            
            'company_id' => null, // Will be set if companies exist
            'fcm_token' => null,
            'last_location_update' => $this->faker->dateTimeBetween('-1 hour', 'now'),
            'remember_token' => Str::random(10),
        ];
    }

    /**
     * Indicate that the rider is active and online.
     */
    public function active(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'active',
            'is_online' => true,
        ]);
    }

    /**
     * Indicate that the rider is approved but not yet active.
     */
    public function approved(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'approved',
            'is_online' => false,
        ]);
    }

    /**
     * Indicate that the rider is pending verification.
     */
    public function pending(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'pending',
            'is_online' => false,
        ]);
    }

    /**
     * Indicate that the rider is suspended.
     */
    public function suspended(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'suspended',
            'is_online' => false,
        ]);
    }

    /**
     * Indicate that the rider is banned.
     */
    public function banned(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'banned',
            'is_online' => false,
        ]);
    }

    /**
     * Indicate that the rider is rejected.
     */
    public function rejected(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'rejected',
            'is_online' => false,
        ]);
    }

    /**
     * Indicate that the rider has high ratings.
     */
    public function highRated(): static
    {
        return $this->state(fn (array $attributes) => [
            'rating' => $this->faker->randomFloat(2, 4.5, 5.0),
            'total_deliveries' => $this->faker->numberBetween(100, 500),
        ]);
    }
}