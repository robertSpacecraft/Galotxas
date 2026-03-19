<?php

namespace Database\Factories;

use App\Models\Category;
use App\Models\Round;
use Illuminate\Database\Eloquent\Factories\Factory;

class RoundFactory extends Factory
{
    protected $model = Round::class;

    public function definition(): array
    {
        $phase = $this->faker->randomElement(['league', 'cup']);

        $stage = match ($phase) {
            'league' => 'matchday',
            'cup' => $this->faker->randomElement(['semifinal', 'third_place', 'final']),
            default => 'matchday',
        };

        $name = match ($stage) {
            'matchday' => 'Jornada ' . $this->faker->numberBetween(1, 14),
            'semifinal' => 'Semifinales',
            'third_place' => '3º y 4º puesto',
            'final' => 'Final',
            default => 'Ronda ' . $this->faker->numberBetween(1, 14),
        };

        return [
            'category_id' => Category::factory(),
            'name' => $name,
            'order' => $this->faker->numberBetween(1, 20),
            'type' => $phase,
            'phase' => $phase,
            'stage' => $stage,
        ];
    }
}
