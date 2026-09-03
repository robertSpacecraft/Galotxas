<?php

namespace Database\Factories;

use App\Models\CategoryOfficialResult;
use App\Models\CategoryOfficialResultMatchSnapshot;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CategoryOfficialResultMatchSnapshot>
 */
class CategoryOfficialResultMatchSnapshotFactory extends Factory
{
    protected $model = CategoryOfficialResultMatchSnapshot::class;

    public function definition(): array
    {
        $homeEntryId = fake()->unique()->numberBetween(1, 1000000);
        $awayEntryId = fake()->unique()->numberBetween(1000001, 2000000);
        $homeWins = fake()->boolean();

        return [
            'official_result_id' => CategoryOfficialResult::factory(),
            'source_game_match_id' => fake()->unique()->numberBetween(1, 1000000),
            'source_round_id' => fake()->numberBetween(1, 1000000),
            'stage' => 'matchday',
            'home_entry_id' => $homeEntryId,
            'away_entry_id' => $awayEntryId,
            'home_score' => $homeWins ? 10 : 7,
            'away_score' => $homeWins ? 7 : 10,
            'winner_entry_id' => $homeWins ? $homeEntryId : $awayEntryId,
        ];
    }
}
