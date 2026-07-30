<?php

namespace Database\Factories;

use App\Models\SchoolLocation;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SchoolLocation>
 */
class SchoolLocationFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => 'Instalación '.$this->faker->unique()->numerify('###'),
            'locality' => $this->faker->city(),
            'address' => $this->faker->optional()->streetAddress(),
            'is_active' => false,
            'sort_order' => 0,
            'admin_notes' => null,
        ];
    }

    public function active(): static
    {
        return $this->state(fn () => [
            'is_active' => true,
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn () => [
            'is_active' => false,
        ]);
    }
}
