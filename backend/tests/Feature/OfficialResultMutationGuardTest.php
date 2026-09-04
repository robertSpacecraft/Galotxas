<?php

namespace Tests\Feature;

use App\Enums\OfficialResultCompetitionPart;
use App\Enums\OfficialResultMutationImpact;
use App\Exceptions\OfficialResultHistoryDeletionBlockedException;
use App\Exceptions\OfficialResultMutationBlockedException;
use App\Models\Category;
use App\Models\CategoryEntry;
use App\Models\CategoryOfficialResult;
use App\Models\CategoryRegistration;
use App\Models\Championship;
use App\Models\GameMatch;
use App\Models\MatchRescheduleRequest;
use App\Models\Player;
use App\Models\Round;
use App\Models\Season;
use App\Models\Team;
use App\Models\User;
use App\Models\Venue;
use App\Services\GenerateCupService;
use App\Services\GenerateLeagueScheduleService;
use App\Services\MatchRescheduleRequestService;
use App\Services\MatchResultReportService;
use App\Services\MatchResultService;
use App\Services\OfficialResultLockService;
use App\Services\OfficialResultMutationGuard;
use App\Services\OfficialResultProtectedDeletionService;
use Carbon\Carbon;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class OfficialResultMutationGuardTest extends TestCase
{
    use RefreshDatabase;

    public function test_common_lock_is_transactional_and_orders_league_before_cup_deterministically(): void
    {
        [$category] = $this->matchFixture('league', 'league', 'matchday');
        $this->official($category, OfficialResultCompetitionPart::CUP);
        $this->official($category, OfficialResultCompetitionPart::LEAGUE);
        $queries = [];

        DB::listen(function (QueryExecuted $query) use (&$queries): void {
            if (str_contains(strtolower($query->sql), 'for update')) {
                $queries[] = strtolower($query->sql);
            }
        });

        $result = DB::transaction(function () use ($category): array {
            $service = app(OfficialResultLockService::class);
            $lock = $service->lockCategoryAndCurrentOfficialResults($category);
            $service->lockRoundsAndMatches([$category->id]);
            $service->lockEntriesAndTeams([$category->id]);

            return [
                'transaction_level' => DB::transactionLevel(),
                'parts' => $lock->currentOfficialResults
                    ->pluck('competition_part')
                    ->map(fn (OfficialResultCompetitionPart $part): string => $part->value)
                    ->all(),
            ];
        });

        $categoryQueryIndex = collect($queries)->search(
            fn (string $sql): bool => str_contains($sql, 'from `categories`')
        );
        $officialQueryIndex = collect($queries)->search(
            fn (string $sql): bool => str_contains($sql, 'from `category_official_results`')
        );
        $roundQueryIndex = collect($queries)->search(
            fn (string $sql): bool => str_contains($sql, 'from `rounds`')
        );
        $matchQueryIndex = collect($queries)->search(
            fn (string $sql): bool => str_contains($sql, 'from `game_matches`')
        );
        $entryQueryIndex = collect($queries)->search(
            fn (string $sql): bool => str_contains($sql, 'from `category_entries`')
        );
        $teamQueryIndex = collect($queries)->search(
            fn (string $sql): bool => str_contains($sql, 'from `teams`')
        );

        $this->assertGreaterThanOrEqual(1, $result['transaction_level']);
        $this->assertSame(['league', 'cup'], $result['parts']);
        $this->assertIsInt($categoryQueryIndex);
        $this->assertIsInt($officialQueryIndex);
        $this->assertIsInt($roundQueryIndex);
        $this->assertIsInt($matchQueryIndex);
        $this->assertIsInt($entryQueryIndex);
        $this->assertIsInt($teamQueryIndex);
        $this->assertLessThan($officialQueryIndex, $categoryQueryIndex);
        $this->assertLessThan($roundQueryIndex, $officialQueryIndex);
        $this->assertLessThan($matchQueryIndex, $roundQueryIndex);
        $this->assertLessThan($entryQueryIndex, $matchQueryIndex);
        $this->assertLessThan($teamQueryIndex, $entryQueryIndex);
        $this->assertStringContainsString('case competition_part', $queries[$officialQueryIndex]);
    }

    #[DataProvider('impactProvider')]
    public function test_central_impact_matrix(
        OfficialResultCompetitionPart $officialPart,
        OfficialResultMutationImpact $impact,
        bool $blocked,
    ): void {
        $category = Category::factory()->create();
        $this->official($category, $officialPart);

        try {
            DB::transaction(fn () => app(OfficialResultMutationGuard::class)
                ->lockAndGuard($category, $impact));

            $this->assertFalse($blocked, 'La mutación debía quedar bloqueada.');
        } catch (OfficialResultMutationBlockedException $exception) {
            $this->assertTrue($blocked, 'La mutación no debía quedar bloqueada.');
            $this->assertSame(OfficialResultMutationBlockedException::MESSAGE, $exception->getMessage());
        }
    }

    public static function impactProvider(): array
    {
        return [
            'league result vs league official' => [
                OfficialResultCompetitionPart::LEAGUE,
                OfficialResultMutationImpact::LEAGUE_RESULT,
                true,
            ],
            'league result vs cup official' => [
                OfficialResultCompetitionPart::CUP,
                OfficialResultMutationImpact::LEAGUE_RESULT,
                true,
            ],
            'cup decisive vs league official' => [
                OfficialResultCompetitionPart::LEAGUE,
                OfficialResultMutationImpact::CUP_DECISIVE,
                false,
            ],
            'cup decisive vs cup official' => [
                OfficialResultCompetitionPart::CUP,
                OfficialResultMutationImpact::CUP_DECISIVE,
                true,
            ],
            'participants vs league official' => [
                OfficialResultCompetitionPart::LEAGUE,
                OfficialResultMutationImpact::PARTICIPANTS,
                true,
            ],
            'participants vs cup official' => [
                OfficialResultCompetitionPart::CUP,
                OfficialResultMutationImpact::PARTICIPANTS,
                true,
            ],
        ];
    }

    public function test_reopened_history_does_not_block_a_normal_mutation(): void
    {
        $category = Category::factory()->create();
        CategoryOfficialResult::factory()->league()->reopened()->create([
            'category_id' => $category->id,
        ]);

        $lock = DB::transaction(fn () => app(OfficialResultMutationGuard::class)
            ->lockAndGuard($category, OfficialResultMutationImpact::LEAGUE_RESULT));

        $this->assertTrue($lock->category->is($category));
        $this->assertCount(0, $lock->currentOfficialResults);
    }

    #[DataProvider('decisiveCupStageProvider')]
    public function test_cup_official_blocks_structurally_decisive_matches(string $stage): void
    {
        [$category, $match, $venue] = $this->matchFixture('cup', 'cup', $stage);
        $this->official($category, OfficialResultCompetitionPart::CUP);
        $originalDate = $match->scheduled_date;

        $this->expectException(OfficialResultMutationBlockedException::class);

        try {
            app(MatchResultService::class)->updateFromAdmin(
                $match,
                $category->id,
                Carbon::parse('2026-10-10 18:00:00'),
                $venue->id,
                'scheduled',
                null,
                null,
                User::factory()->admin()->create(),
            );
        } finally {
            $this->assertEquals($originalDate, $match->fresh()->scheduled_date);
        }
    }

    public static function decisiveCupStageProvider(): array
    {
        return [
            'semifinal' => ['semifinal'],
            'final' => ['final'],
        ];
    }

    public function test_cup_third_place_mutation_remains_allowed_when_identification_is_unambiguous(): void
    {
        [$category, $match, $venue] = $this->matchFixture('cup', 'cup', 'third_place');
        $this->official($category, OfficialResultCompetitionPart::CUP);

        app(MatchResultService::class)->updateFromAdmin(
            $match,
            $category->id,
            Carbon::parse('2026-10-10 18:00:00'),
            $venue->id,
            'scheduled',
            null,
            null,
            User::factory()->admin()->create(),
        );

        $this->assertSame('2026-10-10 18:00:00', $match->fresh()->scheduled_date->format('Y-m-d H:i:s'));
    }

    public function test_ambiguous_match_classification_fails_closed_with_any_current_official(): void
    {
        [$category, $match, $venue] = $this->matchFixture('cup', null, 'third_place');
        $this->official($category, OfficialResultCompetitionPart::LEAGUE);

        $this->expectException(OfficialResultMutationBlockedException::class);

        app(MatchResultService::class)->updateFromAdmin(
            $match,
            $category->id,
            Carbon::parse('2026-10-10 18:00:00'),
            $venue->id,
            'scheduled',
            null,
            null,
            User::factory()->admin()->create(),
        );
    }

    public function test_blade_match_writer_shows_domain_message_and_does_not_mutate(): void
    {
        [$category, $match, $venue] = $this->matchFixture('league', 'league', 'matchday');
        $this->official($category, OfficialResultCompetitionPart::CUP);
        $admin = User::factory()->admin()->create();
        $originalDate = $match->scheduled_date;

        $this->actingAs($admin)
            ->patch(route('admin.categories.matches.update', [$category, $match]), [
                'scheduled_date' => '2026-10-11',
                'scheduled_time' => '19:00',
                'venue_id' => $venue->id,
                'status' => 'scheduled',
                'home_score' => null,
                'away_score' => null,
            ])
            ->assertRedirect()
            ->assertSessionHas('error', OfficialResultMutationBlockedException::MESSAGE);

        $this->assertEquals($originalDate, $match->fresh()->scheduled_date);
    }

    public function test_admin_api_returns_stable_409_envelope_without_mutating_match(): void
    {
        [$category, $match] = $this->matchFixture('cup', 'cup', 'final', [
            'status' => 'submitted',
            'home_score' => 10,
            'away_score' => 7,
            'winner_entry_id' => null,
        ]);
        $this->official($category, OfficialResultCompetitionPart::CUP);
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->postJson("/api/v1/admin/matches/{$match->id}/validate-result")
            ->assertConflict()
            ->assertExactJson([
                'message' => OfficialResultMutationBlockedException::MESSAGE,
                'data' => null,
            ]);

        $this->assertDatabaseHas('game_matches', [
            'id' => $match->id,
            'status' => 'submitted',
            'winner_entry_id' => null,
            'validated_by' => null,
        ]);
    }

    public function test_result_report_is_blocked_before_report_or_match_mutation(): void
    {
        [$category, $match] = $this->matchFixture('league', 'league', 'matchday');
        $homePlayer = $match->homeEntry->player;
        $this->official($category, OfficialResultCompetitionPart::LEAGUE);

        $this->expectException(OfficialResultMutationBlockedException::class);

        try {
            app(MatchResultReportService::class)->submitReport(
                $match,
                $homePlayer->user,
                10,
                7,
            );
        } finally {
            $this->assertDatabaseCount('match_result_reports', 0);
            $this->assertSame('scheduled', $match->fresh()->status->value);
        }
    }

    public function test_reschedule_confirmation_is_blocked_but_preliminary_request_is_preserved(): void
    {
        [$category, $match] = $this->matchFixture('league', 'league', 'matchday');
        $homePlayer = $match->homeEntry->player;
        $awayPlayer = $match->awayEntry->player;
        $requestedVenue = Venue::factory()->create();
        $service = app(MatchRescheduleRequestService::class);
        $originalDate = $match->scheduled_date;

        $request = $service->submitRequest(
            $match,
            $homePlayer->user,
            '2026-11-01',
            '18:30',
            $requestedVenue->id,
            'Cambio solicitado',
        );
        $this->official($category, OfficialResultCompetitionPart::LEAGUE);

        try {
            $service->confirmRequest($match->fresh(), $awayPlayer->user);
            $this->fail('La confirmación debía quedar bloqueada.');
        } catch (OfficialResultMutationBlockedException) {
            $this->assertEquals($originalDate, $match->fresh()->scheduled_date);
            $this->assertSame('submitted', $request->fresh()->status->value);
            $this->assertSame(1, MatchRescheduleRequest::query()->count());
        }
    }

    #[DataProvider('officialPartProvider')]
    public function test_any_current_official_blocks_league_generation(
        OfficialResultCompetitionPart $part,
    ): void {
        $category = Category::factory()->create();
        $this->official($category, $part);

        $this->expectException(OfficialResultMutationBlockedException::class);
        app(GenerateLeagueScheduleService::class)->generate($category);
    }

    public static function officialPartProvider(): array
    {
        return [
            'league' => [OfficialResultCompetitionPart::LEAGUE],
            'cup' => [OfficialResultCompetitionPart::CUP],
        ];
    }

    public function test_cup_official_blocks_cup_regeneration(): void
    {
        $category = Category::factory()->create();
        $this->official($category, OfficialResultCompetitionPart::CUP);

        $this->expectException(OfficialResultMutationBlockedException::class);
        app(GenerateCupService::class)->generateSemifinals($category);
    }

    public function test_league_official_still_allows_cup_generation(): void
    {
        $category = Category::factory()->create();
        CategoryEntry::factory()->count(4)->playerEntry()->create([
            'category_id' => $category->id,
            'status' => 'approved',
        ]);
        $this->official($category, OfficialResultCompetitionPart::LEAGUE);

        app(GenerateCupService::class)->generateSemifinals($category);

        $this->assertDatabaseHas('rounds', [
            'category_id' => $category->id,
            'type' => 'cup',
            'phase' => 'cup',
            'stage' => 'semifinal',
        ]);
    }

    public function test_admin_api_blocks_participant_entry_creation_with_409(): void
    {
        $category = Category::factory()->create();
        $player = Player::factory()->create();
        $this->official($category, OfficialResultCompetitionPart::LEAGUE);

        $this->actingAs(User::factory()->admin()->create())
            ->postJson("/api/v1/admin/categories/{$category->id}/entries", [
                'entry_type' => 'player',
                'player_id' => $player->id,
                'team_id' => null,
            ])
            ->assertConflict()
            ->assertJsonPath('message', OfficialResultMutationBlockedException::MESSAGE);

        $this->assertDatabaseMissing('category_entries', [
            'category_id' => $category->id,
            'player_id' => $player->id,
        ]);
    }

    public function test_registration_entry_team_members_and_player_deletion_are_protected(): void
    {
        $admin = User::factory()->admin()->create();

        $singles = Category::factory()->create([
            'championship_id' => Championship::factory()->create(['type' => 'singles'])->id,
        ]);
        $player = Player::factory()->create();
        $registration = CategoryRegistration::factory()->create([
            'category_id' => $singles->id,
            'player_id' => $player->id,
            'status' => 'approved',
        ]);
        $entry = CategoryEntry::factory()->playerEntry()->create([
            'category_id' => $singles->id,
            'player_id' => $player->id,
            'status' => 'approved',
        ]);
        $this->official($singles, OfficialResultCompetitionPart::CUP);

        $this->actingAs($admin)
            ->delete(route('admin.categories.registrations.destroy', [$singles, $registration]))
            ->assertRedirect()
            ->assertSessionHas('error', OfficialResultMutationBlockedException::MESSAGE);
        $this->assertDatabaseHas('category_entries', ['id' => $entry->id]);

        $this->actingAs($admin)
            ->delete(route('admin.players.destroy', $player))
            ->assertRedirect()
            ->assertSessionHas('error', OfficialResultMutationBlockedException::MESSAGE);
        $this->assertDatabaseHas('players', ['id' => $player->id]);

        $doubles = Category::factory()->create([
            'championship_id' => Championship::factory()->create(['type' => 'doubles'])->id,
        ]);
        $team = Team::factory()->create(['category_id' => $doubles->id]);
        $teamPlayers = Player::factory()->count(2)->create();
        $team->players()->attach($teamPlayers[0]->id, ['role_in_team' => 'front']);
        $team->players()->attach($teamPlayers[1]->id, ['role_in_team' => 'back']);
        $teamEntry = CategoryEntry::factory()->teamEntry()->create([
            'category_id' => $doubles->id,
            'team_id' => $team->id,
            'status' => 'approved',
        ]);
        $this->official($doubles, OfficialResultCompetitionPart::LEAGUE);

        $this->actingAs($admin)
            ->delete(route('admin.categories.teams.destroy', [$doubles, $team]))
            ->assertRedirect()
            ->assertSessionHas('error', OfficialResultMutationBlockedException::MESSAGE);
        $this->assertDatabaseHas('teams', ['id' => $team->id]);
        $this->assertDatabaseHas('category_entries', ['id' => $teamEntry->id]);
        $this->assertDatabaseCount('team_members', 2);
    }

    public function test_team_creation_cannot_change_membership_with_a_current_official(): void
    {
        $championship = Championship::factory()->create(['type' => 'doubles']);
        $category = Category::factory()->create(['championship_id' => $championship->id]);
        $players = Player::factory()->count(2)->create();
        $this->official($category, OfficialResultCompetitionPart::CUP);

        $this->actingAs(User::factory()->admin()->create())
            ->post(route('admin.categories.teams.store', $category), [
                'name' => 'Equipo bloqueado',
                'front_player_id' => $players[0]->id,
                'back_player_id' => $players[1]->id,
            ])
            ->assertRedirect()
            ->assertSessionHas('error', OfficialResultMutationBlockedException::MESSAGE);

        $this->assertDatabaseMissing('teams', [
            'category_id' => $category->id,
            'name' => 'Equipo bloqueado',
        ]);
        $this->assertDatabaseCount('team_members', 0);
    }

    public function test_current_official_blocks_category_and_ancestor_deletions(): void
    {
        $category = Category::factory()->create();
        $championship = $category->championship;
        $season = $championship->season;
        $this->official($category, OfficialResultCompetitionPart::LEAGUE);
        $service = app(OfficialResultProtectedDeletionService::class);

        foreach ([$category, $championship, $season] as $model) {
            try {
                match (true) {
                    $model instanceof Category => $service->deleteCategory($model),
                    $model instanceof Championship => $service->deleteChampionship($model),
                    default => $service->deleteSeason($model),
                };
                $this->fail('El borrado debía quedar bloqueado.');
            } catch (OfficialResultMutationBlockedException $exception) {
                $this->assertSame(OfficialResultMutationBlockedException::MESSAGE, $exception->getMessage());
            }
        }

        $this->assertDatabaseHas('categories', ['id' => $category->id]);
        $this->assertDatabaseHas('championships', ['id' => $championship->id]);
        $this->assertDatabaseHas('seasons', ['id' => $season->id]);
    }

    public function test_reopened_history_keeps_database_restrict_and_has_clear_precheck(): void
    {
        $category = Category::factory()->create();
        CategoryOfficialResult::factory()->league()->reopened()->create([
            'category_id' => $category->id,
        ]);

        $this->expectException(OfficialResultHistoryDeletionBlockedException::class);
        app(OfficialResultProtectedDeletionService::class)->deleteCategory($category);
    }

    public function test_allowed_live_metadata_and_identity_fields_remain_mutable(): void
    {
        $season = Season::factory()->publiclyVisible()->create(['status' => 'planned']);
        $championship = Championship::factory()->publiclyVisible()->create([
            'season_id' => $season->id,
            'type' => 'singles',
            'status' => 'active',
        ]);
        $category = Category::factory()->publiclyVisible()->create([
            'championship_id' => $championship->id,
        ]);
        $team = Team::factory()->create(['category_id' => $category->id]);
        $player = Player::factory()->create();
        $this->official($category, OfficialResultCompetitionPart::LEAGUE);
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->putJson("/api/v1/admin/seasons/{$season->id}", [
                'name' => 'Temporada permitida',
                'status' => 'finished',
                'is_public' => false,
                'start_date' => '2026-01-01',
                'end_date' => '2026-12-31',
            ])
            ->assertOk();

        $this->actingAs($admin)
            ->putJson("/api/v1/admin/championships/{$championship->id}", [
                'season_id' => $season->id,
                'name' => 'Campeonato permitido',
                'description' => 'Metadatos permitidos',
                'type' => 'singles',
                'status' => 'finished',
                'is_public' => false,
                'start_date' => '2026-02-01',
                'end_date' => '2026-08-01',
                'registration_status' => 'closed',
                'registration_starts_at' => null,
                'registration_ends_at' => null,
            ])
            ->assertOk();

        $this->actingAs($admin)
            ->putJson("/api/v1/admin/categories/{$category->id}", [
                'name' => 'Categoría permitida',
                'description' => 'Contenido editorial actualizado',
                'level' => 3,
                'gender' => 'mixed',
                'status' => 'active',
                'is_public' => false,
            ])
            ->assertOk();

        $team->update(['name' => 'Nombre de equipo en vivo']);
        $player->update(['nickname' => 'Alias en vivo']);
        $player->user->update(['name' => 'Nombre en vivo']);

        $this->assertSame('finished', $season->fresh()->status->value);
        $this->assertSame('finished', $championship->fresh()->status);
        $this->assertFalse($category->fresh()->is_public);
        $this->assertSame('Nombre de equipo en vivo', $team->fresh()->name);
        $this->assertSame('Alias en vivo', $player->fresh()->nickname);
        $this->assertSame('Nombre en vivo', $player->user->fresh()->name);
    }

    public function test_championship_rule_change_is_blocked_while_status_change_is_allowed(): void
    {
        $championship = Championship::factory()->create(['type' => 'singles']);
        $category = Category::factory()->create(['championship_id' => $championship->id]);
        $this->official($category, OfficialResultCompetitionPart::CUP);
        $admin = User::factory()->admin()->create();

        $response = $this->actingAs($admin)
            ->putJson("/api/v1/admin/championships/{$championship->id}", [
                'season_id' => $championship->season_id,
                'name' => $championship->name,
                'description' => $championship->description,
                'type' => 'doubles',
                'status' => 'finished',
                'is_public' => false,
                'start_date' => $championship->start_date?->format('Y-m-d'),
                'end_date' => $championship->end_date?->format('Y-m-d'),
                'registration_status' => 'closed',
                'registration_starts_at' => null,
                'registration_ends_at' => null,
            ]);

        $response->assertConflict();
        $this->assertSame('singles', $championship->fresh()->type->value);
        $this->assertSame('active', $championship->fresh()->status);
    }

    private function official(
        Category $category,
        OfficialResultCompetitionPart $part,
    ): CategoryOfficialResult {
        return CategoryOfficialResult::factory()->create([
            'category_id' => $category->id,
            'competition_part' => $part->value,
        ]);
    }

    /**
     * @return array{Category, GameMatch, Venue}
     */
    private function matchFixture(
        string $type,
        ?string $phase,
        ?string $stage,
        array $matchAttributes = [],
    ): array {
        $championship = Championship::factory()->create(['type' => 'singles']);
        $category = Category::factory()->create(['championship_id' => $championship->id]);
        $homePlayer = Player::factory()->create();
        $awayPlayer = Player::factory()->create();
        $homeEntry = CategoryEntry::factory()->playerEntry()->create([
            'category_id' => $category->id,
            'player_id' => $homePlayer->id,
            'status' => 'approved',
        ]);
        $awayEntry = CategoryEntry::factory()->playerEntry()->create([
            'category_id' => $category->id,
            'player_id' => $awayPlayer->id,
            'status' => 'approved',
        ]);
        $round = Round::factory()->create([
            'category_id' => $category->id,
            'type' => $type,
            'phase' => $phase,
            'stage' => $stage,
        ]);
        $venue = Venue::factory()->create();
        $match = GameMatch::factory()->create(array_merge([
            'round_id' => $round->id,
            'venue_id' => $venue->id,
            'home_entry_id' => $homeEntry->id,
            'away_entry_id' => $awayEntry->id,
            'scheduled_date' => '2026-09-20 17:00:00',
            'status' => 'scheduled',
            'home_score' => null,
            'away_score' => null,
            'winner_entry_id' => null,
            'submitted_by' => null,
            'validated_by' => null,
        ], $matchAttributes));

        return [
            $category,
            $match->load(['homeEntry.player.user', 'awayEntry.player.user']),
            $venue,
        ];
    }
}
