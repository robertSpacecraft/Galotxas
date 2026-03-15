<?php

namespace Database\Factories;

use App\Models\Round;
use App\Models\Venue;
use App\Models\CategoryEntry;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\GameMatch>
 */
class GameMatchFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'round_id' => Round::factory(),
            'venue_id' => Venue::factory(),
            'home_entry_id' => CategoryEntry::factory(),
            'away_entry_id' => CategoryEntry::factory(),
            'scheduled_date' => $this->faker->dateTimeBetween('-1 month', '+1 month'),
            'status' => 'scheduled',
        ];
    }
}
