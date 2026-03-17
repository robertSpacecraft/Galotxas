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
        $birthDate = fake()->dateTimeBetween('-40 years', '-10 years');
        $isAdult = $birthDate->diff(now())->y >= 18;

        $nickname = fake()->boolean(45) ? fake()->firstName() : null;
        $firstName = fake()->firstName();
        $lastName = fake()->lastName();

        $slugBase = $nickname ?: $firstName . ' ' . $lastName;

        return [
            'user_id' => User::factory(),
            'nickname' => $nickname,
            'slug' => Str::slug($slugBase . '-' . fake()->unique()->numberBetween(1000, 9999)),
            'dni' => $isAdult ? fake()->unique()->regexify('[0-9]{8}[A-Z]') : null,
            'birth_date' => $birthDate->format('Y-m-d'),
            'gender' => fake()->randomElement(PlayerGender::values()),
            'level' => fake()->numberBetween(1, 10),
            'license_number' => fake()->boolean(50) ? fake()->unique()->bothify('LIC-#####') : null,
            'dominant_hand' => fake()->boolean(80)
                ? fake()->randomElement(['right', 'left', 'both'])
                : null,
            'notes' => fake()->boolean(35) ? fake()->sentence() : null,
            'active' => fake()->boolean(90),
        ];
    }
}
