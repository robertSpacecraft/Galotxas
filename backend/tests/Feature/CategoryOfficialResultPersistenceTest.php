<?php

namespace Tests\Feature;

use App\Enums\OfficialResultCompetitionPart;
use App\Enums\OfficialResultStatus;
use App\Models\Category;
use App\Models\CategoryEntry;
use App\Models\CategoryOfficialCupWinner;
use App\Models\CategoryOfficialLeagueRow;
use App\Models\CategoryOfficialResult;
use App\Models\CategoryOfficialResultMatchSnapshot;
use App\Models\GameMatch;
use App\Models\Player;
use App\Models\Round;
use App\Models\Team;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class CategoryOfficialResultPersistenceTest extends TestCase
{
    use RefreshDatabase;

    public function test_league_and_cup_can_each_have_one_current_official_result(): void
    {
        $category = Category::factory()->create();
        $league = $this->createResult($category, OfficialResultCompetitionPart::LEAGUE);
        $cup = $this->createResult($category, OfficialResultCompetitionPart::CUP);

        $this->assertSame(1, $league->fresh()->current_slot);
        $this->assertSame(1, $cup->fresh()->current_slot);

        try {
            $this->createResult(
                $category,
                OfficialResultCompetitionPart::LEAGUE,
                version: 2
            );
            $this->fail('Una segunda versión oficial de Liga debería rechazarse.');
        } catch (QueryException) {
            $this->assertSame(2, CategoryOfficialResult::query()->count());
        }
    }

    public function test_reopening_releases_the_current_slot_and_preserves_versions(): void
    {
        $category = Category::factory()->create();
        $versionOne = $this->createResult(
            $category,
            OfficialResultCompetitionPart::LEAGUE
        );

        $versionOne->update($this->reopenMetadata());
        $versionTwo = $this->createResult(
            $category,
            OfficialResultCompetitionPart::LEAGUE,
            version: 2
        );
        $versionThree = $this->createResult(
            $category,
            OfficialResultCompetitionPart::LEAGUE,
            version: 3,
            attributes: $this->reopenMetadata()
        );

        $this->assertNull($versionOne->fresh()->current_slot);
        $this->assertSame(1, $versionTwo->fresh()->current_slot);
        $this->assertNull($versionThree->fresh()->current_slot);
        $this->assertSame(1, CategoryOfficialResult::query()->official()->count());
        $this->assertSame(2, CategoryOfficialResult::query()->reopened()->count());
        $this->assertSame([1, 2, 3], CategoryOfficialResult::query()
            ->league()
            ->orderBy('version')
            ->pluck('version')
            ->all());
    }

    public function test_duplicate_version_is_rejected(): void
    {
        $category = Category::factory()->create();
        $this->createResult(
            $category,
            OfficialResultCompetitionPart::LEAGUE,
            attributes: $this->reopenMetadata()
        );

        $this->expectException(QueryException::class);

        $this->createResult(
            $category,
            OfficialResultCompetitionPart::LEAGUE,
            attributes: $this->reopenMetadata()
        );
    }

    #[DataProvider('invalidResultMetadataProvider')]
    public function test_database_rejects_invalid_result_metadata(array $attributes): void
    {
        $this->expectException(QueryException::class);

        CategoryOfficialResult::factory()->create($attributes);
    }

    /**
     * @return array<string, array{array<string, mixed>}>
     */
    public static function invalidResultMetadataProvider(): array
    {
        return [
            'zero version' => [['version' => 0]],
            'short digest' => [['source_digest' => str_repeat('a', 63)]],
            'non hexadecimal digest' => [['source_digest' => str_repeat('z', 64)]],
            'blank actor snapshot' => [['officialized_by_name_snapshot' => '   ']],
            'official with reopen data' => [[
                'reopened_at' => '2026-09-03 10:00:00',
                'reopened_by_name_snapshot' => 'Administración',
                'reopen_reason' => 'Corrección',
            ]],
            'reopened without date' => [[
                'status' => OfficialResultStatus::REOPENED->value,
                'reopened_at' => null,
                'reopened_by_name_snapshot' => 'Administración',
                'reopen_reason' => 'Corrección',
            ]],
            'reopened without actor snapshot' => [[
                'status' => OfficialResultStatus::REOPENED->value,
                'reopened_at' => '2026-09-03 10:00:00',
                'reopened_by_name_snapshot' => null,
                'reopen_reason' => 'Corrección',
            ]],
            'reopened with blank reason' => [[
                'status' => OfficialResultStatus::REOPENED->value,
                'reopened_at' => '2026-09-03 10:00:00',
                'reopened_by_name_snapshot' => 'Administración',
                'reopen_reason' => '   ',
            ]],
        ];
    }

    public function test_models_cast_enums_and_expose_only_snapshot_relations(): void
    {
        $category = Category::factory()->create();
        $result = $this->createResult(
            $category,
            OfficialResultCompetitionPart::LEAGUE
        );
        $leagueRow = CategoryOfficialLeagueRow::factory()
            ->for($result, 'officialResult')
            ->create(['position' => 1, 'source_entry_id' => 101]);
        $matchSnapshot = CategoryOfficialResultMatchSnapshot::factory()
            ->for($result, 'officialResult')
            ->create(['source_game_match_id' => 201]);
        $cupResult = $this->createResult(
            $category,
            OfficialResultCompetitionPart::CUP
        );
        $cupWinner = CategoryOfficialCupWinner::factory()
            ->for($cupResult, 'officialResult')
            ->create();

        $result = $result->fresh();

        $this->assertSame(OfficialResultCompetitionPart::LEAGUE, $result->competition_part);
        $this->assertSame(OfficialResultStatus::OFFICIAL, $result->status);
        $this->assertTrue($result->officialized_at->isImmutable());
        $this->assertFalse($result->isFillable('current_slot'));
        $this->assertArrayNotHasKey('current_slot', $result->toArray());
        $this->assertTrue($category->officialResults->first()->is($result));
        $this->assertTrue($result->category->is($category));
        $this->assertSame(
            $result->officialized_by_user_id,
            $result->officializedBy->getKey()
        );
        $this->assertTrue($result->leagueRows->first()->is($leagueRow));
        $this->assertTrue($result->matchSnapshots->first()->is($matchSnapshot));
        $this->assertTrue($cupResult->cupWinner->is($cupWinner));
    }

    public function test_league_rows_freeze_complete_rankings_and_enforce_uniqueness(): void
    {
        $result = CategoryOfficialResult::factory()->league()->create();
        $row = CategoryOfficialLeagueRow::factory()
            ->for($result, 'officialResult')
            ->create([
                'position' => 1,
                'source_entry_id' => 10,
                'games_for' => 30,
                'games_against' => 35,
                'games_diff' => -5,
                'display_name_snapshot' => 'Participante interno',
                'public_display_name' => 'Alias público',
            ]);

        $this->assertSame(-5, $row->fresh()->games_diff);
        $this->assertSame('Alias público', $row->public_display_name);

        try {
            CategoryOfficialLeagueRow::factory()
                ->for($result, 'officialResult')
                ->create(['position' => 1, 'source_entry_id' => 11]);
            $this->fail('La posición repetida debería rechazarse.');
        } catch (QueryException) {
            $this->assertSame(1, $result->leagueRows()->count());
        }

        try {
            CategoryOfficialLeagueRow::factory()
                ->for($result, 'officialResult')
                ->create(['position' => 0, 'source_entry_id' => 12]);
            $this->fail('La posición cero debería rechazarse.');
        } catch (QueryException) {
            $this->assertSame(1, $result->leagueRows()->count());
        }

        try {
            CategoryOfficialLeagueRow::factory()
                ->for($result, 'officialResult')
                ->create(['position' => 3, 'source_entry_id' => 13, 'points' => -1]);
            $this->fail('Las estadísticas negativas deberían rechazarse.');
        } catch (QueryException) {
            $this->assertSame(1, $result->leagueRows()->count());
        }

        try {
            CategoryOfficialLeagueRow::factory()
                ->for($result, 'officialResult')
                ->create(['position' => 2, 'source_entry_id' => 10]);
            $this->fail('La entrada repetida debería rechazarse.');
        } catch (QueryException) {
            $this->assertSame(1, $result->leagueRows()->count());
        }
    }

    public function test_entry_snapshots_require_a_consistent_player_or_team_source(): void
    {
        $result = CategoryOfficialResult::factory()->league()->create();

        CategoryOfficialLeagueRow::factory()
            ->for($result, 'officialResult')
            ->create([
                'position' => 1,
                'source_entry_id' => 10,
                'entry_type' => 'team',
                'source_player_id' => null,
                'source_team_id' => 20,
            ]);

        $this->expectException(QueryException::class);

        CategoryOfficialLeagueRow::factory()
            ->for($result, 'officialResult')
            ->create([
                'position' => 2,
                'source_entry_id' => 11,
                'entry_type' => 'player',
                'source_player_id' => null,
                'source_team_id' => 21,
            ]);
    }

    public function test_cup_result_has_only_one_winner_snapshot(): void
    {
        $result = CategoryOfficialResult::factory()->cup()->create();
        $winner = CategoryOfficialCupWinner::factory()
            ->for($result, 'officialResult')
            ->create([
                'source_final_match_id' => 500,
                'display_name_snapshot' => 'Campeón interno',
                'public_display_name' => 'Campeón público',
            ]);

        $this->assertTrue($result->cupWinner->is($winner));
        $this->assertSame(500, $winner->source_final_match_id);
        $this->assertSame('Campeón público', $winner->public_display_name);

        $this->expectException(QueryException::class);

        CategoryOfficialCupWinner::factory()
            ->for($result, 'officialResult')
            ->create();
    }

    public function test_match_snapshots_are_unique_and_do_not_follow_live_score_changes(): void
    {
        $category = Category::factory()->create();
        $homeEntry = CategoryEntry::factory()->playerEntry()->for($category)->create();
        $awayEntry = CategoryEntry::factory()->playerEntry()->for($category)->create();
        $round = Round::factory()->for($category)->create([
            'type' => 'cup',
            'phase' => 'cup',
            'stage' => 'final',
        ]);
        $match = GameMatch::factory()->for($round)->create([
            'home_entry_id' => $homeEntry->id,
            'away_entry_id' => $awayEntry->id,
            'home_score' => 12,
            'away_score' => 8,
            'winner_entry_id' => $homeEntry->id,
            'status' => 'validated',
        ]);
        $result = $this->createResult($category, OfficialResultCompetitionPart::CUP);
        $snapshot = CategoryOfficialResultMatchSnapshot::factory()
            ->for($result, 'officialResult')
            ->create([
                'source_game_match_id' => $match->id,
                'source_round_id' => $round->id,
                'stage' => 'final',
                'home_entry_id' => $homeEntry->id,
                'away_entry_id' => $awayEntry->id,
                'home_score' => 12,
                'away_score' => 8,
                'winner_entry_id' => $homeEntry->id,
            ]);

        $match->update([
            'home_score' => 7,
            'away_score' => 12,
            'winner_entry_id' => $awayEntry->id,
        ]);

        $this->assertSame(12, $snapshot->fresh()->home_score);
        $this->assertSame(8, $snapshot->away_score);
        $this->assertSame($homeEntry->id, $snapshot->winner_entry_id);

        CategoryOfficialResultMatchSnapshot::factory()
            ->for($result, 'officialResult')
            ->create([
                'source_game_match_id' => $match->id + 100000,
                'stage' => 'semifinal',
            ]);

        $this->assertSame(2, $result->matchSnapshots()->count());

        $this->expectException(QueryException::class);

        CategoryOfficialResultMatchSnapshot::factory()
            ->for($result, 'officialResult')
            ->create(['source_game_match_id' => $match->id]);
    }

    #[DataProvider('invalidMatchSnapshotProvider')]
    public function test_match_snapshots_reject_invalid_outcomes(array $attributes): void
    {
        $result = CategoryOfficialResult::factory()->create();

        $this->expectException(QueryException::class);

        CategoryOfficialResultMatchSnapshot::factory()
            ->for($result, 'officialResult')
            ->create(array_merge([
                'home_entry_id' => 10,
                'away_entry_id' => 20,
                'home_score' => 10,
                'away_score' => 7,
                'winner_entry_id' => 10,
            ], $attributes));
    }

    /**
     * @return array<string, array{array<string, int>}>
     */
    public static function invalidMatchSnapshotProvider(): array
    {
        return [
            'same entry on both sides' => [['away_entry_id' => 10]],
            'tied score' => [['away_score' => 10]],
            'winner outside the match' => [['winner_entry_id' => 30]],
        ];
    }

    public function test_category_and_ancestors_cannot_delete_official_history(): void
    {
        $category = Category::factory()->create();
        $result = $this->createResult(
            $category,
            OfficialResultCompetitionPart::LEAGUE
        );
        $championship = $category->championship;
        $season = $championship->season;

        foreach ([$category, $championship, $season] as $model) {
            try {
                $model->delete();
                $this->fail('El histórico oficial debería bloquear el borrado ancestral.');
            } catch (QueryException) {
                $this->assertDatabaseHas('category_official_results', ['id' => $result->id]);
            }
        }
    }

    public function test_deleting_actor_keeps_result_and_name_snapshot(): void
    {
        $actor = User::factory()->create(['name' => 'Ada', 'lastname' => 'Admin']);
        $reopenedBy = User::factory()->create(['name' => 'Rita', 'lastname' => 'Revisora']);
        $result = CategoryOfficialResult::factory()->create([
            'officialized_by_user_id' => $actor->id,
            'officialized_by_name_snapshot' => 'Ada Admin',
        ]);
        $result->update(array_merge($this->reopenMetadata(), [
            'reopened_by_user_id' => $reopenedBy->id,
            'reopened_by_name_snapshot' => 'Rita Revisora',
        ]));

        $actor->delete();
        $reopenedBy->delete();

        $result = $result->fresh();
        $this->assertNull($result->officialized_by_user_id);
        $this->assertNull($result->reopened_by_user_id);
        $this->assertSame('Ada Admin', $result->officialized_by_name_snapshot);
        $this->assertSame('Rita Revisora', $result->reopened_by_name_snapshot);
    }

    public function test_source_deletions_do_not_destroy_snapshots(): void
    {
        $category = Category::factory()->create();
        $player = Player::factory()->create();
        $playerEntry = CategoryEntry::factory()->for($category)->playerEntry()->create([
            'player_id' => $player->id,
        ]);
        $team = Team::factory()->for($category)->create();
        $teamEntry = CategoryEntry::factory()->for($category)->teamEntry()->create([
            'team_id' => $team->id,
        ]);
        $round = Round::factory()->for($category)->create([
            'type' => 'league',
            'phase' => 'league',
            'stage' => 'matchday',
        ]);
        $match = GameMatch::factory()->for($round)->create([
            'home_entry_id' => $playerEntry->id,
            'away_entry_id' => $teamEntry->id,
            'home_score' => 10,
            'away_score' => 7,
            'winner_entry_id' => $playerEntry->id,
            'status' => 'validated',
        ]);
        $result = $this->createResult(
            $category,
            OfficialResultCompetitionPart::LEAGUE
        );
        $playerRow = CategoryOfficialLeagueRow::factory()
            ->for($result, 'officialResult')
            ->create([
                'position' => 1,
                'source_entry_id' => $playerEntry->id,
                'source_player_id' => $player->id,
            ]);
        $teamRow = CategoryOfficialLeagueRow::factory()
            ->for($result, 'officialResult')
            ->create([
                'position' => 2,
                'source_entry_id' => $teamEntry->id,
                'entry_type' => 'team',
                'source_player_id' => null,
                'source_team_id' => $team->id,
            ]);
        $matchSnapshot = CategoryOfficialResultMatchSnapshot::factory()
            ->for($result, 'officialResult')
            ->create([
                'source_game_match_id' => $match->id,
                'home_entry_id' => $playerEntry->id,
                'away_entry_id' => $teamEntry->id,
                'winner_entry_id' => $playerEntry->id,
            ]);

        $player->delete();
        $team->delete();

        $this->assertDatabaseMissing('game_matches', ['id' => $match->id]);
        $this->assertDatabaseHas('category_official_league_rows', ['id' => $playerRow->id]);
        $this->assertDatabaseHas('category_official_league_rows', ['id' => $teamRow->id]);
        $this->assertDatabaseHas(
            'category_official_result_match_snapshots',
            ['id' => $matchSnapshot->id]
        );
    }

    public function test_deleting_a_version_cascades_only_its_technical_snapshots(): void
    {
        $result = CategoryOfficialResult::factory()->create();
        $row = CategoryOfficialLeagueRow::factory()
            ->for($result, 'officialResult')
            ->create();
        $snapshot = CategoryOfficialResultMatchSnapshot::factory()
            ->for($result, 'officialResult')
            ->create();

        $result->delete();

        $this->assertDatabaseMissing('category_official_league_rows', ['id' => $row->id]);
        $this->assertDatabaseMissing(
            'category_official_result_match_snapshots',
            ['id' => $snapshot->id]
        );
    }

    public function test_snapshot_schema_excludes_sensitive_personal_fields(): void
    {
        foreach ([
            'dni',
            'email',
            'phone',
            'address',
            'birth_date',
            'license_number',
            'guardian_name',
            'confirmation_token_hash',
            'notes',
            'profile_photo_path',
        ] as $field) {
            $this->assertFalse(Schema::hasColumn('category_official_league_rows', $field));
            $this->assertFalse(Schema::hasColumn('category_official_cup_winners', $field));
        }
    }

    private function createResult(
        Category $category,
        OfficialResultCompetitionPart $part,
        int $version = 1,
        array $attributes = []
    ): CategoryOfficialResult {
        return CategoryOfficialResult::factory()
            ->for($category)
            ->create(array_merge([
                'competition_part' => $part->value,
                'version' => $version,
            ], $attributes));
    }

    /** @return array<string, mixed> */
    private function reopenMetadata(): array
    {
        return [
            'status' => OfficialResultStatus::REOPENED->value,
            'reopened_at' => now(),
            'reopened_by_user_id' => null,
            'reopened_by_name_snapshot' => 'Administración histórica',
            'reopen_reason' => 'Se requiere una nueva versión oficial.',
        ];
    }
}
