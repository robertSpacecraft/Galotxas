<?php

namespace Tests\Feature\Api\V1;

use App\Enums\ChampionshipStatus;
use App\Enums\SeasonStatus;
use App\Models\Category;
use App\Models\CategoryEntry;
use App\Models\CategoryOfficialCupWinner;
use App\Models\CategoryOfficialLeagueRow;
use App\Models\CategoryOfficialResult;
use App\Models\Championship;
use App\Models\Player;
use App\Models\Season;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\CreatesOfficialCupFixture;
use Tests\TestCase;

class PublicCategoryOfficialResultApiTest extends TestCase
{
    use CreatesOfficialCupFixture;
    use RefreshDatabase;

    public function test_public_category_without_results_returns_exact_empty_envelope_and_is_get_only(): void
    {
        $category = $this->createPublicCategory();

        $this->assertGuest();
        $this->getJson($this->endpoint($category))
            ->assertOk()
            ->assertExactJson([
                'message' => null,
                'data' => [
                    'league' => null,
                    'cup' => null,
                ],
            ]);
        $this->postJson($this->endpoint($category))->assertMethodNotAllowed();
    }

    public function test_current_league_uses_only_snapshot_rows_in_persisted_position_order(): void
    {
        $category = $this->createPublicCategory();
        $league = CategoryOfficialResult::factory()->league()->create([
            'category_id' => $category->id,
            'version' => 2,
            'officialized_at' => '2026-09-06 10:20:30',
            'officialized_by_name_snapshot' => 'ACTOR-PRIVADO-LIGA',
            'source_digest' => str_repeat('a', 64),
        ]);
        CategoryOfficialLeagueRow::factory()
            ->for($league, 'officialResult')
            ->create([
                'position' => 2,
                'source_entry_id' => 202,
                'source_player_id' => null,
                'source_team_id' => 302,
                'entry_type' => 'team',
                'display_name_snapshot' => 'INTERNO-EQUIPO-DOS',
                'public_display_name' => 'Equip dos',
                'played' => 9,
                'wins' => 6,
                'losses' => 3,
                'points' => 12,
                'games_for' => 70,
                'games_against' => 55,
                'games_diff' => 15,
            ]);
        CategoryOfficialLeagueRow::factory()
            ->for($league, 'officialResult')
            ->create([
                'position' => 1,
                'source_entry_id' => 201,
                'source_player_id' => 301,
                'source_team_id' => null,
                'entry_type' => 'player',
                'display_name_snapshot' => 'INTERNO-JUGADOR-UNO',
                'public_display_name' => 'Alias uno',
                'played' => 9,
                'wins' => 8,
                'losses' => 1,
                'points' => 16,
                'games_for' => 90,
                'games_against' => 54,
                'games_diff' => 36,
            ]);

        $response = $this->getJson($this->endpoint($category));

        $response
            ->assertOk()
            ->assertExactJson([
                'message' => null,
                'data' => [
                    'league' => [
                        'version' => 2,
                        'officialized_at' => $league->officialized_at->toISOString(),
                        'ranking' => [
                            [
                                'position' => 1,
                                'entry_type' => 'player',
                                'public_display_name' => 'Alias uno',
                                'played' => 9,
                                'wins' => 8,
                                'losses' => 1,
                                'points' => 16,
                                'games_for' => 90,
                                'games_against' => 54,
                                'games_diff' => 36,
                            ],
                            [
                                'position' => 2,
                                'entry_type' => 'team',
                                'public_display_name' => 'Equip dos',
                                'played' => 9,
                                'wins' => 6,
                                'losses' => 3,
                                'points' => 12,
                                'games_for' => 70,
                                'games_against' => 55,
                                'games_diff' => 15,
                            ],
                        ],
                    ],
                    'cup' => null,
                ],
            ]);

        foreach ([
            'ACTOR-PRIVADO-LIGA',
            'INTERNO-EQUIPO-DOS',
            'INTERNO-JUGADOR-UNO',
            str_repeat('a', 64),
            'source_entry_id',
            'identity_projection',
            'public_anonymized_at',
        ] as $privateValue) {
            $this->assertStringNotContainsString($privateValue, $response->getContent());
        }
    }

    public function test_current_cup_uses_only_persisted_champion_snapshot(): void
    {
        $category = $this->createPublicCategory();
        $cup = CategoryOfficialResult::factory()->cup()->create([
            'category_id' => $category->id,
            'officialized_at' => '2026-09-06 11:21:31',
            'officialized_by_name_snapshot' => 'ACTOR-PRIVADO-COPA',
            'source_digest' => str_repeat('b', 64),
        ]);
        CategoryOfficialCupWinner::factory()
            ->for($cup, 'officialResult')
            ->create([
                'source_entry_id' => 401,
                'source_player_id' => null,
                'source_team_id' => 501,
                'entry_type' => 'team',
                'source_final_match_id' => 601,
                'display_name_snapshot' => 'CAMPEON-INTERNO',
                'public_display_name' => 'Equip campió',
            ]);

        $response = $this->getJson($this->endpoint($category));

        $response
            ->assertOk()
            ->assertExactJson([
                'message' => null,
                'data' => [
                    'league' => null,
                    'cup' => [
                        'version' => 1,
                        'officialized_at' => $cup->officialized_at->toISOString(),
                        'champion' => [
                            'entry_type' => 'team',
                            'public_display_name' => 'Equip campió',
                        ],
                    ],
                ],
            ]);

        foreach ([
            'ACTOR-PRIVADO-COPA',
            'CAMPEON-INTERNO',
            str_repeat('b', 64),
            'source_final_match_id',
            'runner_up',
            'third_place',
            'match_snapshots',
        ] as $privateValue) {
            $this->assertStringNotContainsString($privateValue, $response->getContent());
        }
    }

    public function test_current_league_and_cup_are_published_independently_without_lazy_loading(): void
    {
        $category = $this->createPublicCategory();
        $league = CategoryOfficialResult::factory()->league()->create([
            'category_id' => $category->id,
            'version' => 3,
        ]);
        CategoryOfficialLeagueRow::factory()
            ->for($league, 'officialResult')
            ->create([
                'position' => 1,
                'source_entry_id' => 701,
                'public_display_name' => 'Líder congelado',
            ]);
        $cup = CategoryOfficialResult::factory()->cup()->create([
            'category_id' => $category->id,
            'version' => 4,
        ]);
        CategoryOfficialCupWinner::factory()
            ->for($cup, 'officialResult')
            ->create([
                'source_entry_id' => 702,
                'public_display_name' => 'Campeón congelado',
            ]);

        $preventedLazyLoadingBeforeTest = Model::preventsLazyLoading();
        Model::preventLazyLoading();

        try {
            $response = $this->getJson($this->endpoint($category));
        } finally {
            Model::preventLazyLoading($preventedLazyLoadingBeforeTest);
        }

        $response
            ->assertOk()
            ->assertJsonPath('data.league.version', 3)
            ->assertJsonPath('data.league.ranking.0.public_display_name', 'Líder congelado')
            ->assertJsonPath('data.cup.version', 4)
            ->assertJsonPath('data.cup.champion.public_display_name', 'Campeón congelado')
            ->assertJsonCount(1, 'data.league.ranking');
    }

    public function test_reopened_part_is_null_without_hiding_the_other_current_part(): void
    {
        $leagueReopenedCategory = $this->createPublicCategory();
        CategoryOfficialResult::factory()->league()->reopened()->create([
            'category_id' => $leagueReopenedCategory->id,
        ]);
        $currentCup = CategoryOfficialResult::factory()->cup()->create([
            'category_id' => $leagueReopenedCategory->id,
        ]);
        CategoryOfficialCupWinner::factory()
            ->for($currentCup, 'officialResult')
            ->create(['source_entry_id' => 801, 'public_display_name' => 'Copa vigente']);

        $this->getJson($this->endpoint($leagueReopenedCategory))
            ->assertOk()
            ->assertJsonPath('data.league', null)
            ->assertJsonPath('data.cup.champion.public_display_name', 'Copa vigente');

        $cupReopenedCategory = $this->createPublicCategory();
        CategoryOfficialResult::factory()->cup()->reopened()->create([
            'category_id' => $cupReopenedCategory->id,
        ]);
        $currentLeague = CategoryOfficialResult::factory()->league()->create([
            'category_id' => $cupReopenedCategory->id,
        ]);
        CategoryOfficialLeagueRow::factory()
            ->for($currentLeague, 'officialResult')
            ->create(['position' => 1, 'source_entry_id' => 802, 'public_display_name' => 'Liga vigente']);

        $this->getJson($this->endpoint($cupReopenedCategory))
            ->assertOk()
            ->assertJsonPath('data.league.ranking.0.public_display_name', 'Liga vigente')
            ->assertJsonPath('data.cup', null);

        $bothReopenedCategory = $this->createPublicCategory();
        CategoryOfficialResult::factory()->league()->reopened()->create([
            'category_id' => $bothReopenedCategory->id,
        ]);
        CategoryOfficialResult::factory()->cup()->reopened()->create([
            'category_id' => $bothReopenedCategory->id,
        ]);

        $this->getJson($this->endpoint($bothReopenedCategory))
            ->assertOk()
            ->assertExactJson([
                'message' => null,
                'data' => [
                    'league' => null,
                    'cup' => null,
                ],
            ]);
    }

    public function test_only_v2_is_exposed_after_v1_is_reopened_and_history_never_leaks(): void
    {
        $category = $this->createPublicCategory();
        $leagueV1 = CategoryOfficialResult::factory()->league()->reopened()->create([
            'category_id' => $category->id,
            'version' => 1,
            'source_digest' => str_repeat('c', 64),
        ]);
        CategoryOfficialLeagueRow::factory()
            ->for($leagueV1, 'officialResult')
            ->create([
                'position' => 1,
                'source_entry_id' => 901,
                'display_name_snapshot' => 'LIGA-V1-INTERNA',
                'public_display_name' => 'LIGA-V1-PUBLICA',
            ]);
        $leagueV2 = CategoryOfficialResult::factory()->league()->create([
            'category_id' => $category->id,
            'version' => 2,
        ]);
        CategoryOfficialLeagueRow::factory()
            ->for($leagueV2, 'officialResult')
            ->create([
                'position' => 1,
                'source_entry_id' => 902,
                'public_display_name' => 'LIGA-V2-PUBLICA',
            ]);

        $cupV1 = CategoryOfficialResult::factory()->cup()->reopened()->create([
            'category_id' => $category->id,
            'version' => 1,
            'source_digest' => str_repeat('d', 64),
        ]);
        CategoryOfficialCupWinner::factory()
            ->for($cupV1, 'officialResult')
            ->create([
                'source_entry_id' => 903,
                'display_name_snapshot' => 'COPA-V1-INTERNA',
                'public_display_name' => 'COPA-V1-PUBLICA',
            ]);
        $cupV2 = CategoryOfficialResult::factory()->cup()->create([
            'category_id' => $category->id,
            'version' => 2,
        ]);
        CategoryOfficialCupWinner::factory()
            ->for($cupV2, 'officialResult')
            ->create([
                'source_entry_id' => 904,
                'public_display_name' => 'COPA-V2-PUBLICA',
            ]);

        $response = $this->getJson($this->endpoint($category));

        $response
            ->assertOk()
            ->assertJsonPath('data.league.version', 2)
            ->assertJsonPath('data.league.ranking.0.public_display_name', 'LIGA-V2-PUBLICA')
            ->assertJsonPath('data.cup.version', 2)
            ->assertJsonPath('data.cup.champion.public_display_name', 'COPA-V2-PUBLICA')
            ->assertJsonMissingPath('data.history');

        foreach ([
            'LIGA-V1-INTERNA',
            'LIGA-V1-PUBLICA',
            'COPA-V1-INTERNA',
            'COPA-V1-PUBLICA',
            str_repeat('c', 64),
            str_repeat('d', 64),
            'reopen_reason',
        ] as $historicalValue) {
            $this->assertStringNotContainsString($historicalValue, $response->getContent());
        }
    }

    public function test_frozen_public_identity_is_used_without_reading_live_sources_and_anonymization_fails_closed(): void
    {
        $category = $this->createPublicCategory();
        $user = User::factory()->create([
            'name' => 'Nombre vivo original',
            'lastname' => 'Apellido vivo original',
        ]);
        $player = Player::factory()->create([
            'user_id' => $user->id,
            'nickname' => 'Alias vivo original',
        ]);
        $entry = CategoryEntry::factory()->playerEntry()->create([
            'category_id' => $category->id,
            'player_id' => $player->id,
            'status' => 'approved',
        ]);
        $league = CategoryOfficialResult::factory()->league()->create([
            'category_id' => $category->id,
            'officialized_by_name_snapshot' => 'ACTOR-INTERNO-IDENTIDAD',
        ]);
        CategoryOfficialLeagueRow::factory()
            ->for($league, 'officialResult')
            ->create([
                'position' => 1,
                'source_entry_id' => $entry->id,
                'source_player_id' => $player->id,
                'display_name_snapshot' => 'NOMBRE-INTERNO-CONGELADO',
                'public_display_name' => 'Alias público congelado',
            ]);
        CategoryOfficialLeagueRow::factory()
            ->for($league, 'officialResult')
            ->create([
                'position' => 2,
                'source_entry_id' => 1002,
                'source_player_id' => 2002,
                'identity_projection' => 'anonymous',
                'display_name_snapshot' => 'ANONIMO-INTERNO',
                'public_display_name' => 'Participante',
            ]);
        CategoryOfficialLeagueRow::factory()
            ->for($league, 'officialResult')
            ->create([
                'position' => 3,
                'source_entry_id' => 1003,
                'source_player_id' => 2003,
                'display_name_snapshot' => 'ANONIMIZADO-INTERNO',
                'public_display_name' => 'ALIAS-RETIRADO',
                'public_anonymized_at' => '2026-09-06 12:00:00',
            ]);
        CategoryOfficialLeagueRow::factory()
            ->for($league, 'officialResult')
            ->create([
                'position' => 4,
                'source_entry_id' => 1004,
                'source_player_id' => 2004,
                'display_name_snapshot' => 'VACIO-INTERNO',
                'public_display_name' => '   ',
            ]);
        $cup = CategoryOfficialResult::factory()->cup()->create([
            'category_id' => $category->id,
        ]);
        CategoryOfficialCupWinner::factory()
            ->for($cup, 'officialResult')
            ->create([
                'source_entry_id' => 1005,
                'source_player_id' => 2005,
                'display_name_snapshot' => 'CAMPEON-INTERNO-SIN-NOMBRE',
                'public_display_name' => null,
            ]);

        $player->update(['nickname' => 'ALIAS-VIVO-MODIFICADO']);
        $user->update([
            'name' => 'NOMBRE-VIVO-MODIFICADO',
            'lastname' => 'APELLIDO-VIVO-MODIFICADO',
        ]);

        $queries = [];
        DB::listen(static function ($query) use (&$queries): void {
            $queries[] = $query->sql;
        });

        $response = $this->getJson($this->endpoint($category));

        $response
            ->assertOk()
            ->assertJsonPath('data.league.ranking.0.public_display_name', 'Alias público congelado')
            ->assertJsonPath('data.league.ranking.1.public_display_name', 'Participante')
            ->assertJsonPath('data.league.ranking.2.public_display_name', 'Participante')
            ->assertJsonPath('data.league.ranking.3.public_display_name', 'Participante')
            ->assertJsonPath('data.cup.champion.public_display_name', 'Participante');

        foreach ([
            'ALIAS-VIVO-MODIFICADO',
            'NOMBRE-VIVO-MODIFICADO',
            'APELLIDO-VIVO-MODIFICADO',
            'NOMBRE-INTERNO-CONGELADO',
            'ANONIMO-INTERNO',
            'ANONIMIZADO-INTERNO',
            'ALIAS-RETIRADO',
            'VACIO-INTERNO',
            'CAMPEON-INTERNO-SIN-NOMBRE',
            'ACTOR-INTERNO-IDENTIDAD',
        ] as $privateValue) {
            $this->assertStringNotContainsString($privateValue, $response->getContent());
        }

        $requestSql = strtolower(implode("\n", $queries));
        foreach ([
            'category_entries',
            'players',
            'teams',
            'users',
            'public_identity_authorizations',
        ] as $liveTable) {
            $this->assertStringNotContainsString($liveTable, $requestSql);
        }
    }

    public function test_effective_visibility_is_required_but_operational_status_does_not_define_officiality(): void
    {
        $publicSeason = Season::factory()->publiclyVisible()->create([
            'status' => SeasonStatus::FINISHED->value,
        ]);
        $publicChampionship = Championship::factory()->publiclyVisible()->create([
            'season_id' => $publicSeason->id,
            'status' => ChampionshipStatus::CANCELLED->value,
        ]);
        $publicCategory = Category::factory()->publiclyVisible()->create([
            'championship_id' => $publicChampionship->id,
            'status' => 'pending',
        ]);
        $officialLeague = CategoryOfficialResult::factory()->league()->create([
            'category_id' => $publicCategory->id,
        ]);
        CategoryOfficialLeagueRow::factory()
            ->for($officialLeague, 'officialResult')
            ->create([
                'position' => 1,
                'source_entry_id' => 1051,
                'public_display_name' => 'Resultado oficial cerrado',
            ]);

        $this->getJson($this->endpoint($publicCategory))
            ->assertOk()
            ->assertJsonPath(
                'data.league.ranking.0.public_display_name',
                'Resultado oficial cerrado',
            )
            ->assertJsonPath('data.cup', null);

        $privateCategory = Category::factory()->privatelyVisible()->create([
            'championship_id' => $publicChampionship->id,
        ]);
        $privateChampionship = Championship::factory()->privatelyVisible()->create([
            'season_id' => $publicSeason->id,
        ]);
        $categoryUnderPrivateChampionship = Category::factory()->publiclyVisible()->create([
            'championship_id' => $privateChampionship->id,
        ]);
        $privateSeason = Season::factory()->privatelyVisible()->create();
        $championshipUnderPrivateSeason = Championship::factory()->publiclyVisible()->create([
            'season_id' => $privateSeason->id,
        ]);
        $categoryUnderPrivateSeason = Category::factory()->publiclyVisible()->create([
            'championship_id' => $championshipUnderPrivateSeason->id,
        ]);

        foreach ([
            $privateCategory,
            $categoryUnderPrivateChampionship,
            $categoryUnderPrivateSeason,
        ] as $hiddenCategory) {
            $this->getJson($this->endpoint($hiddenCategory))->assertNotFound();
        }
    }

    public function test_corrupt_current_results_fail_the_whole_endpoint_without_live_fallback(): void
    {
        $leagueFixture = $this->createReadySinglesCup();
        $missingLeagueRows = $leagueFixture['category'];
        $this->makeCategoryBranchPublic($missingLeagueRows);
        CategoryOfficialResult::factory()->league()->create([
            'category_id' => $missingLeagueRows->id,
        ]);
        $validCup = CategoryOfficialResult::factory()->cup()->create([
            'category_id' => $missingLeagueRows->id,
        ]);
        CategoryOfficialCupWinner::factory()
            ->for($validCup, 'officialResult')
            ->create(['source_entry_id' => 1101]);

        $this->assertIntegrityFailure($missingLeagueRows);

        $cupFixture = $this->createReadySinglesCup();
        $missingCupWinner = $cupFixture['category'];
        $this->makeCategoryBranchPublic($missingCupWinner);
        $validLeague = CategoryOfficialResult::factory()->league()->create([
            'category_id' => $missingCupWinner->id,
        ]);
        CategoryOfficialLeagueRow::factory()
            ->for($validLeague, 'officialResult')
            ->create(['position' => 1, 'source_entry_id' => 1102]);
        CategoryOfficialResult::factory()->cup()->create([
            'category_id' => $missingCupWinner->id,
        ]);

        $this->assertIntegrityFailure($missingCupWinner);

        $leagueWithCupEvidence = $this->createPublicCategory();
        $corruptLeague = CategoryOfficialResult::factory()->league()->create([
            'category_id' => $leagueWithCupEvidence->id,
        ]);
        CategoryOfficialLeagueRow::factory()
            ->for($corruptLeague, 'officialResult')
            ->create(['position' => 1, 'source_entry_id' => 1103]);
        CategoryOfficialCupWinner::factory()
            ->for($corruptLeague, 'officialResult')
            ->create(['source_entry_id' => 1104]);

        $this->assertIntegrityFailure($leagueWithCupEvidence);

        $cupWithLeagueEvidence = $this->createPublicCategory();
        $corruptCup = CategoryOfficialResult::factory()->cup()->create([
            'category_id' => $cupWithLeagueEvidence->id,
        ]);
        CategoryOfficialCupWinner::factory()
            ->for($corruptCup, 'officialResult')
            ->create(['source_entry_id' => 1105]);
        CategoryOfficialLeagueRow::factory()
            ->for($corruptCup, 'officialResult')
            ->create(['position' => 1, 'source_entry_id' => 1106]);

        $this->assertIntegrityFailure($cupWithLeagueEvidence);
    }

    /**
     * @param  array<string, mixed>  $seasonAttributes
     * @param  array<string, mixed>  $championshipAttributes
     * @param  array<string, mixed>  $categoryAttributes
     */
    private function createPublicCategory(
        array $seasonAttributes = [],
        array $championshipAttributes = [],
        array $categoryAttributes = [],
    ): Category {
        $season = Season::factory()
            ->publiclyVisible()
            ->create($seasonAttributes);
        $championship = Championship::factory()
            ->publiclyVisible()
            ->create(array_merge(
                ['season_id' => $season->id],
                $championshipAttributes,
            ));

        return Category::factory()
            ->publiclyVisible()
            ->create(array_merge(
                ['championship_id' => $championship->id],
                $categoryAttributes,
            ));
    }

    private function endpoint(Category $category): string
    {
        return '/api/v1/categories/'.$category->id.'/official-results';
    }

    private function makeCategoryBranchPublic(Category $category): void
    {
        $championship = $category->championship;
        $season = $championship->season;

        $season->forceFill(['is_public' => true])->save();
        $championship->forceFill(['is_public' => true])->save();
        $category->forceFill(['is_public' => true])->save();
    }

    private function assertIntegrityFailure(Category $category): void
    {
        $this->getJson($this->endpoint($category))
            ->assertStatus(500)
            ->assertExactJson([
                'message' => 'No se ha podido obtener el resultado oficial.',
                'data' => null,
            ]);
    }
}
