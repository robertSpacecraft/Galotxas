<?php

namespace Database\Factories;

use App\Models\CategoryOfficialLeagueRow;
use App\Models\CategoryOfficialResult;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CategoryOfficialLeagueRow>
 */
class CategoryOfficialLeagueRowFactory extends Factory
{
    protected $model = CategoryOfficialLeagueRow::class;

    public function definition(): array
    {
        $played = fake()->numberBetween(1, 12);
        $wins = fake()->numberBetween(0, $played);
        $gamesFor = fake()->numberBetween(0, 120);
        $gamesAgainst = fake()->numberBetween(0, 120);

        return [
            'official_result_id' => CategoryOfficialResult::factory()->league(),
            'position' => fake()->unique()->numberBetween(1, 10000),
            'source_entry_id' => fake()->unique()->numberBetween(1, 1000000),
            'source_player_id' => fake()->numberBetween(1, 1000000),
            'source_team_id' => null,
            'entry_type' => 'player',
            'identity_projection' => 'alias',
            'display_name_snapshot' => fake()->name(),
            'public_display_name' => fake()->firstName(),
            'public_anonymized_at' => null,
            'played' => $played,
            'wins' => $wins,
            'losses' => $played - $wins,
            'points' => fake()->numberBetween(0, 36),
            'games_for' => $gamesFor,
            'games_against' => $gamesAgainst,
            'games_diff' => $gamesFor - $gamesAgainst,
        ];
    }
}
