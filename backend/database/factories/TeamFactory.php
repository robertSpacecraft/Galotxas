<?php

namespace Database\Factories;

use App\Models\Category;
use App\Models\Team;
use Illuminate\Database\Eloquent\Factories\Factory;

class TeamFactory extends Factory
{
    protected $model = Team::class;

    public function definition(): array
    {
        return [
            'category_id' => Category::factory(),
            'name' => 'Equipo ' . strtoupper($this->faker->unique()->bothify('??##')),
        ];
    }
}
