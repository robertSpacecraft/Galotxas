<?php

namespace Database\Factories;

use App\Models\SchoolLevel;
use App\Models\SchoolProgram;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SchoolLevel>
 */
class SchoolLevelFactory extends Factory
{
    public function definition(): array
    {
        return [
            'school_program_id' => SchoolProgram::factory(),
            'name' => 'Nivel '.$this->faker->unique()->numerify('###'),
            'minimum_age' => null,
            'maximum_age' => null,
            'is_active' => false,
            'is_public' => false,
            'sort_order' => 0,
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

    public function publiclyVisible(): static
    {
        return $this->state(fn () => [
            'is_public' => true,
        ]);
    }

    public function privatelyVisible(): static
    {
        return $this->state(fn () => [
            'is_public' => false,
        ]);
    }
}
