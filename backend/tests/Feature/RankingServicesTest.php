<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\CategoryEntry;
use App\Models\Championship;
use App\Models\GameMatch;
use App\Models\Player;
use App\Models\Round;
use App\Models\Team;
use App\Services\Ranking\BuildAllTimeRankingService;
use App\Services\Ranking\BuildCategoryRankingService;
use App\Services\Ranking\BuildChampionshipRankingService;
use App\Services\Ranking\BuildSeasonRankingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class RankingServicesTest extends TestCase
{
    use RefreshDatabase;

    public function test_basic_category_ranking_calculates_points_statistics_and_positions(): void
    {
        [$category, $round, $entries] = $this->createSinglesCategory(['Alba', 'Berta']);
        $this->createValidatedMatch($round, $entries[0], $entries[1], 10, 7);

        $ranking = $this->categoryRanking($category);

        $this->assertSame([$entries[0]->id, $entries[1]->id], $ranking->pluck('entry_id')->all());
        $this->assertSame([1, 2], $ranking->pluck('position')->all());
        $this->assertSame(3, $ranking[0]['points']);
        $this->assertSame(1, $ranking[0]['played']);
        $this->assertSame(1, $ranking[0]['wins']);
        $this->assertSame(0, $ranking[0]['losses']);
        $this->assertSame(3, $ranking[0]['games_diff']);
        $this->assertSame(0, $ranking[1]['points']);
    }

    public function test_head_to_head_decides_when_exactly_two_entries_are_tied_on_points(): void
    {
        [$category, $round, $entries] = $this->createSinglesCategory(['A', 'B', 'C']);

        $this->createValidatedMatch($round, $entries[0], $entries[1], 10, 8);
        $this->createValidatedMatch($round, $entries[1], $entries[2], 10, 0);
        $this->createValidatedMatch($round, $entries[2], $entries[0], 10, 8);

        $ranking = $this->categoryRanking($category);

        $this->assertSame(4, $ranking->firstWhere('entry_id', $entries[0]->id)['points']);
        $this->assertSame(4, $ranking->firstWhere('entry_id', $entries[1]->id)['points']);
        $this->assertGreaterThan(
            $ranking->firstWhere('entry_id', $entries[0]->id)['games_diff'],
            $ranking->firstWhere('entry_id', $entries[1]->id)['games_diff']
        );
        $this->assertSame([$entries[0]->id, $entries[1]->id, $entries[2]->id], $ranking->pluck('entry_id')->all());
    }

    public function test_three_way_cycle_uses_global_criteria_instead_of_pairwise_comparisons(): void
    {
        [$category, $round, $entries] = $this->createSinglesCategory(['Zulu', 'Alfa', 'Mike']);

        $this->createValidatedMatch($round, $entries[0], $entries[1], 10, 0);
        $this->createValidatedMatch($round, $entries[1], $entries[2], 10, 5);
        $this->createValidatedMatch($round, $entries[2], $entries[0], 10, 7);

        $firstCalculation = $this->categoryRanking($category);
        $secondCalculation = $this->categoryRanking($category);

        $expectedOrder = [$entries[0]->id, $entries[2]->id, $entries[1]->id];

        $this->assertSame([3, 3, 3], $firstCalculation->pluck('points')->all());
        $this->assertSame([7, -2, -5], $firstCalculation->pluck('games_diff')->all());
        $this->assertSame($expectedOrder, $firstCalculation->pluck('entry_id')->all());
        $this->assertSame($expectedOrder, $secondCalculation->pluck('entry_id')->all());
    }

    public function test_total_tie_uses_entry_id_as_final_stable_criterion(): void
    {
        [$category, $round, $entries] = $this->createSinglesCategory(['Igual', 'Igual', 'Igual']);

        $this->createValidatedMatch($round, $entries[0], $entries[1], 10, 0);
        $this->createValidatedMatch($round, $entries[1], $entries[2], 10, 0);
        $this->createValidatedMatch($round, $entries[2], $entries[0], 10, 0);

        $expectedOrder = collect($entries)->pluck('id')->sort()->values()->all();

        $this->assertSame($expectedOrder, $this->categoryRanking($category)->pluck('entry_id')->all());
        $this->assertSame($expectedOrder, $this->categoryRanking($category)->pluck('entry_id')->all());
    }

    public function test_entries_without_matches_have_valid_zero_statistics_and_stable_positions(): void
    {
        [$category, , $entries] = $this->createSinglesCategory(['Sin partidos', 'Sin partidos', 'Sin partidos']);

        $ranking = $this->categoryRanking($category);

        $this->assertSame(collect($entries)->pluck('id')->sort()->values()->all(), $ranking->pluck('entry_id')->all());

        foreach ($ranking as $row) {
            $this->assertSame(0, $row['played']);
            $this->assertSame(0, $row['wins']);
            $this->assertSame(0, $row['losses']);
            $this->assertSame(0, $row['points']);
            $this->assertSame(0, $row['games_diff']);
        }
    }

    public function test_doubles_ranking_identifies_and_orders_team_entries(): void
    {
        $championship = Championship::factory()->create(['type' => 'doubles']);
        $category = Category::factory()->create(['championship_id' => $championship->id]);
        $round = $this->createLeagueRound($category);
        $firstEntry = $this->createTeamEntry($category, 'Equip Roig');
        $secondEntry = $this->createTeamEntry($category, 'Equip Blau');

        $this->createValidatedMatch($round, $firstEntry, $secondEntry, 10, 8);

        $ranking = $this->categoryRanking($category);

        $this->assertSame([$firstEntry->id, $secondEntry->id], $ranking->pluck('entry_id')->all());
        $this->assertSame(['Equip Roig', 'Equip Blau'], $ranking->pluck('name')->all());
        $this->assertSame('team', $ranking[0]['entry']->entry_type);
        $this->assertSame(3, $ranking[0]['points']);
    }

    public function test_all_time_win_rate_is_returned_on_zero_to_one_hundred_scale(): void
    {
        [$category, $round, $entries, $players] = $this->createSinglesCategory(['Principal', 'Rival 1', 'Rival 2']);

        $this->createValidatedMatch($round, $entries[0], $entries[1], 10, 4);
        $this->createValidatedMatch($round, $entries[2], $entries[0], 10, 4);

        $serviceRow = app(BuildAllTimeRankingService::class)
            ->build()
            ->firstWhere('player_id', $players[0]->id);

        $this->assertNotNull($serviceRow);
        $this->assertSame(2, $serviceRow['played']);
        $this->assertSame(1, $serviceRow['wins']);
        $this->assertSame(50.0, $serviceRow['win_rate']);

        $response = $this->getJson('/api/v1/rankings/all-time')->assertOk();
        $resourceRow = collect($response->json('data'))->firstWhere('player_id', $players[0]->id);

        $this->assertNotNull($resourceRow);
        $this->assertSame(50.0, (float) $resourceRow['win_rate']);
    }

    public function test_championship_and_season_rankings_remain_coherent_with_category_results(): void
    {
        [$category, $round, $entries, $players] = $this->createSinglesCategory(['Agregada A', 'Agregada B']);
        $this->createValidatedMatch($round, $entries[0], $entries[1], 10, 8);

        $categoryRow = $this->categoryRanking($category)->firstWhere('entry_id', $entries[0]->id);
        $championshipRow = app(BuildChampionshipRankingService::class)
            ->build($category->championship)
            ->firstWhere('player_id', $players[0]->id);
        $seasonRow = app(BuildSeasonRankingService::class)
            ->build($category->championship->season)
            ->firstWhere('player_id', $players[0]->id);

        $this->assertSame(3, $categoryRow['points']);
        $this->assertSame(3.0, $championshipRow['raw_points']);
        $this->assertSame($championshipRow['raw_points'], $seasonRow['raw_points']);
        $this->assertSame($championshipRow['weighted_points'], $seasonRow['weighted_points']);
        $this->assertSame($championshipRow['played'], $seasonRow['played']);
        $this->assertSame($championshipRow['wins'], $seasonRow['wins']);
        $this->assertSame($championshipRow['games_diff'], $seasonRow['games_diff']);
    }

    public function test_my_panel_ranking_keeps_its_private_contract(): void
    {
        [$category, $round, $entries, $players] = $this->createSinglesCategory(['Mi jugador', 'Rival']);
        $this->createValidatedMatch($round, $entries[0], $entries[1], 10, 7);
        Sanctum::actingAs($players[0]->user);

        $this->getJson('/api/v1/me/rankings')
            ->assertOk()
            ->assertJsonPath('data.0.category.id', $category->id)
            ->assertJsonPath('data.0.entry_type', 'player')
            ->assertJsonPath('data.0.entry_name', 'Mi jugador')
            ->assertJsonPath('data.0.position', 1)
            ->assertJsonPath('data.0.played', 1)
            ->assertJsonPath('data.0.wins', 1)
            ->assertJsonPath('data.0.losses', 0)
            ->assertJsonPath('data.0.points', 3)
            ->assertJsonMissingPath('data.0.entry');
    }

    /**
     * @return array{Category, Round, array<int, CategoryEntry>, array<int, Player>}
     */
    private function createSinglesCategory(array $nicknames): array
    {
        $championship = Championship::factory()->create(['type' => 'singles']);
        $category = Category::factory()->create([
            'championship_id' => $championship->id,
            'level' => 2,
        ]);
        $round = $this->createLeagueRound($category);
        $entries = [];
        $players = [];

        foreach ($nicknames as $nickname) {
            $player = Player::factory()->create(['nickname' => $nickname]);
            $entry = CategoryEntry::query()->create([
                'category_id' => $category->id,
                'entry_type' => 'player',
                'player_id' => $player->id,
                'team_id' => null,
                'status' => 'approved',
            ]);

            $players[] = $player;
            $entries[] = $entry;
        }

        return [$category, $round, $entries, $players];
    }

    private function createLeagueRound(Category $category): Round
    {
        return Round::factory()->create([
            'category_id' => $category->id,
            'type' => 'league',
            'phase' => 'league',
            'stage' => 'matchday',
        ]);
    }

    private function createTeamEntry(Category $category, string $name): CategoryEntry
    {
        $team = Team::factory()->create([
            'category_id' => $category->id,
            'name' => $name,
        ]);
        $players = Player::factory()->count(2)->create();

        $team->players()->attach($players[0]->id, ['role_in_team' => 'front']);
        $team->players()->attach($players[1]->id, ['role_in_team' => 'back']);

        return CategoryEntry::query()->create([
            'category_id' => $category->id,
            'entry_type' => 'team',
            'player_id' => null,
            'team_id' => $team->id,
            'status' => 'approved',
        ]);
    }

    private function createValidatedMatch(
        Round $round,
        CategoryEntry $homeEntry,
        CategoryEntry $awayEntry,
        int $homeScore,
        int $awayScore
    ): GameMatch {
        return GameMatch::factory()->create([
            'round_id' => $round->id,
            'venue_id' => null,
            'home_entry_id' => $homeEntry->id,
            'away_entry_id' => $awayEntry->id,
            'status' => 'validated',
            'home_score' => $homeScore,
            'away_score' => $awayScore,
        ]);
    }

    private function categoryRanking(Category $category)
    {
        return app(BuildCategoryRankingService::class)->build($category);
    }
}
