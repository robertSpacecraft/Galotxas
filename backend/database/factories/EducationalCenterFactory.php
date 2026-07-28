<?php

namespace Database\Factories;

use App\Models\EducationalCenter;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EducationalCenter>
 */
class EducationalCenterFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => 'Centro educativo '.$this->faker->unique()->numerify('###'),
            'locality' => $this->faker->city(),
            'contact_name' => null,
            'contact_phone' => null,
            'contact_email' => null,
            'is_active' => false,
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

    public function withContact(): static
    {
        return $this->state(fn () => [
            'contact_name' => $this->faker->name(),
            'contact_phone' => $this->faker->phoneNumber(),
            'contact_email' => $this->faker->safeEmail(),
        ]);
    }

    public function withoutContact(): static
    {
        return $this->state(fn () => [
            'contact_name' => null,
            'contact_phone' => null,
            'contact_email' => null,
        ]);
    }
}
