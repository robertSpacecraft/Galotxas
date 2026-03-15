<?php

namespace Database\Factories;

use App\Models\Category;
use App\Models\Player;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\CategoryEntry>
 */
class CategoryEntryFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'category_id' => Category::factory(),
            'entry_type' => 'player',
            'player_id' => Player::factory(),
            'team_id' => null,
            'status' => 'approved',
        ];
    }
}
