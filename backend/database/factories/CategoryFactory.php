<?php

namespace Database\Factories;

use App\Models\Championship;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Category>
 */
class CategoryFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = $this->faker->randomElement(['1ª Categoría', '2ª Categoría', '3ª Categoría', 'Femenina']);
        return [
            'championship_id' => Championship::factory(),
            'name' => $name,
            'slug' => Str::slug($name . '-' . Str::random(5)),
            'level' => $this->faker->numberBetween(1, 4),
            'category_type' => $this->faker->randomElement(['open', 'female', 'mixed']),
            'status' => 'active',
        ];
    }
}
