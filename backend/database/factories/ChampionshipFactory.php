<?php

namespace Database\Factories;

use App\Models\Season;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Championship>
 */
class ChampionshipFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'season_id' => Season::factory(),
            'name' => $this->faker->randomElement(['Campionat Mà a Mà', 'Campionat de Dobles', 'Trofeu de Primavera']),
            'description' => $this->faker->sentence(),
            'type' => $this->faker->randomElement(['singles', 'doubles']),
            'status' => 'active',
        ];
    }
}
