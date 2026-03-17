<?php

namespace Database\Factories;

use App\Models\Season;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Championship>
 */
class ChampionshipFactory extends Factory
{
    public function definition(): array
    {
        $name = $this->faker->randomElement([
            'Campionat Mà a Mà',
            'Campionat de Dobles',
            'Trofeu de Primavera',
        ]);

        $startDate = $this->faker->dateTimeBetween('-2 months', '+1 month');
        $endDate = (clone $startDate)->modify('+2 months');

        return [
            'season_id' => Season::factory(),
            'name' => $name,
            'slug' => Str::slug($name . '-' . Str::random(5)),
            'description' => $this->faker->sentence(),
            'type' => $this->faker->randomElement(['singles', 'doubles']),
            'start_date' => $startDate->format('Y-m-d'),
            'end_date' => $endDate->format('Y-m-d'),
            'image_path' => null,
            'status' => 'active',
        ];
    }
}
