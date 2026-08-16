<?php

namespace Database\Factories;

use App\Models\Sponsor;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Sponsor>
 */
class SponsorFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => $this->faker->company(),
            'logo_key' => sprintf('sponsors/%s.png', Str::uuid()),
            'logo_width' => 600,
            'logo_height' => 300,
            'website_url' => null,
            'sort_order' => 0,
            'is_active' => false,
            'starts_at' => null,
            'ends_at' => null,
        ];
    }

    public function active(): static
    {
        return $this->state(fn () => [
            'is_active' => true,
            'starts_at' => null,
            'ends_at' => null,
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn () => [
            'is_active' => false,
        ]);
    }

    public function scheduled(): static
    {
        return $this->state(fn () => [
            'is_active' => true,
            'starts_at' => now()->addDay(),
            'ends_at' => null,
        ]);
    }

    public function expired(): static
    {
        return $this->state(fn () => [
            'is_active' => true,
            'starts_at' => null,
            'ends_at' => now()->subSecond(),
        ]);
    }
}
