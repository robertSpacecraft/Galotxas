<?php

namespace Database\Factories;

use App\Enums\CategoryGender;
use App\Models\Category;
use App\Models\Championship;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Category>
 */
class CategoryFactory extends Factory
{
    public function definition(): array
    {
        $level = $this->faker->numberBetween(1, 6);
        $name = $level.'ª Categoría';

        return [
            'championship_id' => Championship::factory(),
            'name' => $name,
            'slug' => Str::slug($name.'-'.Str::random(5)),
            'level' => $level,
            'gender' => $this->faker->randomElement([
                CategoryGender::MALE->value,
                CategoryGender::FEMALE->value,
                CategoryGender::MIXED->value,
            ]),
            'description' => $this->faker->sentence(),
            'image_path' => null,
            'status' => 'active',
            'is_public' => false,
        ];
    }

    public function publiclyVisible(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_public' => true,
        ]);
    }

    public function privatelyVisible(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_public' => false,
        ]);
    }
}
