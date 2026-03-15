<?php

namespace Database\Factories;

use App\Enums\CategoryGender;
use App\Models\Championship;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Category>
 */
class CategoryFactory extends Factory
{
    public function definition(): array
    {
        $level = $this->faker->numberBetween(1, 6);

        return [
            'championship_id' => Championship::factory(),
            'name' => $level . 'ª Categoría',
            'slug' => Str::slug($level . 'a-categoria-' . Str::random(5)),
            'level' => $level,
            'gender' => $this->faker->randomElement([
                CategoryGender::MALE->value,
                CategoryGender::FEMALE->value,
                CategoryGender::MIXED->value,
            ]),
            'status' => 'active',
        ];
    }
}
