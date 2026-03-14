<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Player>
 */
class PlayerFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = $this->faker->name();
        return [
            'name' => $name,
            'slug' => Str::slug($name),
            'dni' => $this->faker->unique()->regexify('[0-9]{8}[A-Z]'),
            'birth_date' => $this->faker->date('Y-m-d', '-18 years'),
            'gender' => $this->faker->randomElement(['M', 'F']),
            'level' => $this->faker->numberBetween(1, 4),
        ];
    }
}
