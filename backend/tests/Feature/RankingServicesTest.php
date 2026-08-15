<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\CategoryEntry;
use App\Models\Championship;
use App\Models\GameMatch;
use App\Models\Player;
use App\Models\Round;
use App\Models\Season;
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

    public function test_category_ranking_applies_base_points_for_close_results_symmetrically(): void
    {
        $cases = [
            [10, 7, 3, 0],
            [10, 8, 2, 1],
            [10, 9, 2, 1],
            [7, 10, 0, 3],
            [8, 10, 1, 2],
            [9, 10, 1, 2],
        ];

        foreach ($cases as $index => [$homeScore, $awayScore, $homePoints, $awayPoints]) {
            [$category, $round, $entries] = $this->createSinglesCategory([
                "Local {$index}",
                "Visitante {$index}",
            ]);
            $this->createValidatedMatch($round, $entries[0], $entries[1], $homeScore, $awayScore);

            $ranking = $this->categoryRanking($category)->keyBy('entry_id');

            $this->assertSame($homePoints, $ranking[$entries[0]->id]['points']);
            $this->assertSame($awayPoints, $ranking[$entries[1]->id]['points']);
            $this->assertSame(3, $ranking->sum('points'));
        }
    }

    public function test_head_to_head_decides_when_exactly_two_entries_are_tied_on_points(): void
    {
        [$category, $round, $entries] = $this->createSinglesCategory(['A', 'B', 'C', 'D']);

        $this->createValidatedMatch($round, $entries[0], $entries[1], 10, 9);
        $this->createValidatedMatch($round, $entries[2], $entries[0], 10, 8);
        $this->createValidatedMatch($round, $entries[1], $entries[2], 10, 8);
        $this->createValidatedMatch($round, $entries[2], $entries[3], 10, 8);

        $ranking = $this->categoryRanking($category);

        $this->assertSame(3, $ranking->firstWhere('entry_id', $entries[0]->id)['points']);
        $this->assertSame(3, $ranking->firstWhere('entry_id', $entries[1]->id)['points']);
        $this->assertGreaterThan(
            $ranking->firstWhere('entry_id', $entries[0]->id)['games_diff'],
            $ranking->firstWhere('entry_id', $entries[1]->id)['games_diff']
        );
        $this->assertSame(
            [$entries[2]->id, $entries[0]->id, $entries[1]->id, $entries[3]->id],
            $ranking->pluck('entry_id')->all()
        );
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

        $this->createValidatedMatch($round, $firstEntry, $secondEntry, 12, 8);

        $ranking = $this->categoryRanking($category);

        $this->assertSame([$firstEntry->id, $secondEntry->id], $ranking->pluck('entry_id')->all());
        $this->assertSame(['Equip Roig', 'Equip Blau'], $ranking->pluck('name')->all());
        $this->assertSame('team', $ranking[0]['entry']->entry_type);
        $this->assertSame(2, $ranking[0]['points']);
        $this->assertSame(1, $ranking[1]['points']);
    }

    public function test_category_uses_only_league_matches_while_aggregate_rankings_use_all_validated_rounds(): void
    {
        [$category, $leagueRound, $entries, $players] = $this->createSinglesCategory(['Liga A', 'Liga B']);
        $cupRound = Round::factory()->create([
            'category_id' => $category->id,
            'type' => 'cup',
            'phase' => 'cup',
            'stage' => 'semifinal',
        ]);

        $this->createValidatedMatch($leagueRound, $entries[0], $entries[1], 10, 7);
        $this->createValidatedMatch($cupRound, $entries[1], $entries[0], 10, 8);

        $categoryRows = $this->categoryRanking($category)->keyBy('entry_id');
        $championshipRows = app(BuildChampionshipRankingService::class)
            ->build($category->championship)
            ->keyBy('player_id');
        $seasonRows = app(BuildSeasonRankingService::class)
            ->build($category->championship->season)
            ->keyBy('player_id');
        $allTimeRows = app(BuildAllTimeRankingService::class)
            ->build()
            ->keyBy('player_id');

        $this->assertSame(3, $categoryRows[$entries[0]->id]['points']);
        $this->assertSame(0, $categoryRows[$entries[1]->id]['points']);

        foreach ([$championshipRows, $seasonRows, $allTimeRows] as $rows) {
            $this->assertSame(4.0, $rows[$players[0]->id]['raw_points']);
            $this->assertSame(2.0, $rows[$players[1]->id]['raw_points']);
            $this->assertSame(2, $rows[$players[0]->id]['played']);
            $this->assertSame(2, $rows[$players[1]->id]['played']);
        }
    }

    public function test_all_time_win_rate_is_returned_on_zero_to_one_hundred_scale(): void
    {
        [$category, $round, $entries, $players] = $this->createSinglesCategory(
            ['Principal', 'Rival 1', 'Rival 2'],
            public: true
        );

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
        $resourceRow = collect($response->json('data'))
            ->firstWhere('public_display_name', 'Principal');

        $this->assertNotNull($resourceRow);
        $this->assertSame(50.0, (float) $resourceRow['win_rate']);
    }

    public function test_close_result_is_coherent_in_category_championship_season_and_all_time_rankings(): void
    {
        [$category, $round, $entries, $players] = $this->createSinglesCategory(['Agregada A', 'Agregada B']);
        $this->createValidatedMatch($round, $entries[0], $entries[1], 10, 8);

        $categoryRows = $this->categoryRanking($category)->keyBy('entry_id');
        $championshipRows = app(BuildChampionshipRankingService::class)
            ->build($category->championship)
            ->keyBy('player_id');
        $seasonRows = app(BuildSeasonRankingService::class)
            ->build($category->championship->season)
            ->keyBy('player_id');
        $allTimeRows = app(BuildAllTimeRankingService::class)
            ->build()
            ->keyBy('player_id');

        $this->assertSame(2, $categoryRows[$entries[0]->id]['points']);
        $this->assertSame(1, $categoryRows[$entries[1]->id]['points']);

        foreach ([$championshipRows, $seasonRows, $allTimeRows] as $rows) {
            $this->assertSame(2.0, $rows[$players[0]->id]['raw_points']);
            $this->assertSame(1.0, $rows[$players[1]->id]['raw_points']);
            $this->assertEqualsWithDelta(2.70, $rows[$players[0]->id]['weighted_points'], 0.00001);
            $this->assertEqualsWithDelta(1.35, $rows[$players[1]->id]['weighted_points'], 0.00001);
        }

        $this->assertSame(1, $championshipRows[$players[0]->id]['position']);
        $this->assertSame(2, $championshipRows[$players[1]->id]['position']);
        $this->assertSame(1, $seasonRows[$players[0]->id]['position']);
        $this->assertSame(2, $seasonRows[$players[1]->id]['position']);
        $this->assertEqualsWithDelta(2.70, $allTimeRows[$players[0]->id]['weighted_points_per_match'], 0.00001);
        $this->assertEqualsWithDelta(1.35, $allTimeRows[$players[1]->id]['weighted_points_per_match'], 0.00001);
        $this->assertNull($allTimeRows[$players[0]->id]['position']);
        $this->assertNull($allTimeRows[$players[1]->id]['position']);
    }

    public function test_doubles_base_points_are_split_by_role_before_level_weighting(): void
    {
        $championship = Championship::factory()->create(['type' => 'doubles']);
        $category = Category::factory()->create([
            'championship_id' => $championship->id,
            'level' => 2,
        ]);
        $round = $this->createLeagueRound($category);
        [$homeEntry, $homePlayers] = $this->createTeamEntryWithPlayers($category, 'Equip local');
        [$awayEntry, $awayPlayers] = $this->createTeamEntryWithPlayers($category, 'Equip visitant');

        $this->createValidatedMatch($round, $homeEntry, $awayEntry, 12, 8);

        $rows = app(BuildChampionshipRankingService::class)
            ->build($championship)
            ->keyBy('player_id');

        $this->assertEqualsWithDelta(0.50, $rows[$homePlayers[0]->id]['raw_points'], 0.00001);
        $this->assertEqualsWithDelta(1.50, $rows[$homePlayers[1]->id]['raw_points'], 0.00001);
        $this->assertEqualsWithDelta(0.25, $rows[$awayPlayers[0]->id]['raw_points'], 0.00001);
        $this->assertEqualsWithDelta(0.75, $rows[$awayPlayers[1]->id]['raw_points'], 0.00001);
        $this->assertEqualsWithDelta(0.675, $rows[$homePlayers[0]->id]['weighted_points'], 0.00001);
        $this->assertEqualsWithDelta(2.025, $rows[$homePlayers[1]->id]['weighted_points'], 0.00001);
        $this->assertEqualsWithDelta(0.3375, $rows[$awayPlayers[0]->id]['weighted_points'], 0.00001);
        $this->assertEqualsWithDelta(1.0125, $rows[$awayPlayers[1]->id]['weighted_points'], 0.00001);
    }

    public function test_my_panel_ranking_keeps_its_private_contract(): void
    {
        [$category, $round, $entries, $players] = $this->createSinglesCategory(['Mi jugador', 'Rival']);
        $this->createValidatedMatch($round, $entries[0], $entries[1], 10, 8);
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
            ->assertJsonPath('data.0.points', 2)
            ->assertJsonMissingPath('data.0.entry');

        Sanctum::actingAs($players[1]->user);

        $this->getJson('/api/v1/me/rankings')
            ->assertOk()
            ->assertJsonPath('data.0.position', 2)
            ->assertJsonPath('data.0.wins', 0)
            ->assertJsonPath('data.0.losses', 1)
            ->assertJsonPath('data.0.points', 1);
    }

    /**
     * @return array{Category, Round, array<int, CategoryEntry>, array<int, Player>}
     */
    private function createSinglesCategory(array $nicknames, bool $public = false): array
    {
        $seasonFactory = $public
            ? Season::factory()->publiclyVisible()
            : Season::factory()->privatelyVisible();
        $season = $seasonFactory->create();
        $championshipFactory = $public
            ? Championship::factory()->publiclyVisible()
            : Championship::factory()->privatelyVisible();
        $championship = $championshipFactory->create([
            'season_id' => $season->id,
            'type' => 'singles',
        ]);
        $categoryFactory = $public
            ? Category::factory()->publiclyVisible()
            : Category::factory()->privatelyVisible();
        $category = $categoryFactory->create([
            'championship_id' => $championship->id,
            'level' => 2,
        ]);
        $round = $this->createLeagueRound($category);
        $entries = [];
        $players = [];

        foreach ($nicknames as $nickname) {
            $player = Player::factory()->create([
                'nickname' => $nickname,
                'birth_date' => '1990-01-01',
            ]);
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
        return $this->createTeamEntryWithPlayers($category, $name)[0];
    }

    /**
     * @return array{CategoryEntry, array<int, Player>}
     */
    private function createTeamEntryWithPlayers(Category $category, string $name): array
    {
        $team = Team::factory()->create([
            'category_id' => $category->id,
            'name' => $name,
        ]);
        $players = Player::factory()->count(2)->create()->values();

        $team->players()->attach($players[0]->id, ['role_in_team' => 'front']);
        $team->players()->attach($players[1]->id, ['role_in_team' => 'back']);

        $entry = CategoryEntry::query()->create([
            'category_id' => $category->id,
            'entry_type' => 'team',
            'player_id' => null,
            'team_id' => $team->id,
            'status' => 'approved',
        ]);

        return [$entry, $players->all()];
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
