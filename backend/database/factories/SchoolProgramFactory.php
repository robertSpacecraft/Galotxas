<?php

namespace Database\Factories;

use App\Models\SchoolLocation;
use App\Models\SchoolProgram;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SchoolProgram>
 */
class SchoolProgramFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => 'Programa '.$this->faker->unique()->numerify('###'),
            'is_public' => false,
            'enrollments_open' => false,
            'default_school_location_id' => null,
            'contact_phone' => null,
            'contact_email' => null,
            'sort_order' => 0,
        ];
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

    public function enrollmentsOpen(): static
    {
        return $this->state(fn () => [
            'enrollments_open' => true,
        ]);
    }

    public function enrollmentsClosed(): static
    {
        return $this->state(fn () => [
            'enrollments_open' => false,
        ]);
    }

    public function withDefaultLocation(): static
    {
        return $this->for(SchoolLocation::factory()->active(), 'defaultLocation');
    }

    public function withoutDefaultLocation(): static
    {
        return $this->state(fn () => [
            'default_school_location_id' => null,
        ]);
    }
}
