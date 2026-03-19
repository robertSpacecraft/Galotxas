<?php

namespace Database\Factories;

use App\Models\Category;
use App\Models\CategoryEntry;
use App\Models\Player;
use App\Models\Team;
use Illuminate\Database\Eloquent\Factories\Factory;

class CategoryEntryFactory extends Factory
{
    protected $model = CategoryEntry::class;

    public function definition(): array
    {
        $entryType = $this->faker->randomElement(['player', 'team']);

        return [
            'category_id' => Category::factory(),
            'entry_type' => $entryType,
            'player_id' => $entryType === 'player' ? Player::factory() : null,
            'team_id' => $entryType === 'team' ? Team::factory() : null,
            'status' => $this->faker->randomElement(['pending', 'approved', 'rejected']),
        ];
    }

    public function playerEntry(): static
    {
        return $this->state(fn () => [
            'entry_type' => 'player',
            'player_id' => Player::factory(),
            'team_id' => null,
        ]);
    }

    public function teamEntry(): static
    {
        return $this->state(fn () => [
            'entry_type' => 'team',
            'player_id' => null,
            'team_id' => Team::factory(),
        ]);
    }
}
