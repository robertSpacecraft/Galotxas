<?php

namespace Database\Factories;

use App\Enums\PlayerGender;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Player>
 */
class PlayerFactory extends Factory
{
    public function definition(): array
    {
        $firstName = fake()->firstName();
        $lastName = fake()->lastName();

        return [
            'user_id' => User::factory(),
            'slug' => Str::slug($firstName . '-' . $lastName . '-' . fake()->unique()->numberBetween(1000, 9999)),
            'dni' => fake()->unique()->regexify('[0-9]{8}[A-Z]'),
            'birth_date' => fake()->date(),
            'gender' => fake()->randomElement(PlayerGender::values()),
            'level' => fake()->numberBetween(1, 10),
            'active' => fake()->boolean(90),
        ];
    }
}
