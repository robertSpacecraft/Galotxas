<?php

namespace Database\Factories;

use App\Models\CategoryOfficialCupWinner;
use App\Models\CategoryOfficialResult;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CategoryOfficialCupWinner>
 */
class CategoryOfficialCupWinnerFactory extends Factory
{
    protected $model = CategoryOfficialCupWinner::class;

    public function definition(): array
    {
        return [
            'official_result_id' => CategoryOfficialResult::factory()->cup(),
            'source_entry_id' => fake()->numberBetween(1, 1000000),
            'source_player_id' => fake()->numberBetween(1, 1000000),
            'source_team_id' => null,
            'entry_type' => 'player',
            'source_final_match_id' => fake()->numberBetween(1, 1000000),
            'identity_projection' => 'alias',
            'display_name_snapshot' => fake()->name(),
            'public_display_name' => fake()->firstName(),
            'public_anonymized_at' => null,
        ];
    }
}
