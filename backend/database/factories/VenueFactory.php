<?php

namespace Database\Factories;

use App\Models\Venue;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Venue>
 */
class VenueFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'court_number' => $this->faker->unique()->numberBetween(1, 2147483647),
            'name' => 'Carrer '.$this->faker->city(),
            'location' => $this->faker->city(),
        ];
    }
}
