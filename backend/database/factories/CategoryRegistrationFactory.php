<?php

namespace Database\Factories;

use App\Models\Category;
use App\Models\CategoryRegistration;
use App\Models\Player;
use Illuminate\Database\Eloquent\Factories\Factory;

class CategoryRegistrationFactory extends Factory
{
    protected $model = CategoryRegistration::class;

    public function definition(): array
    {
        return [
            'category_id' => Category::factory(),
            'player_id' => Player::factory(),
            'status' => $this->faker->randomElement([
                'pending',
                'approved',
                'rejected',
            ]),
        ];
    }
}
