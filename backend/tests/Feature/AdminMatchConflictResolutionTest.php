<?php

namespace Tests\Feature;

use App\Models\GameMatch;
use App\Models\MatchResultReport;
use App\Models\Player;
use App\Models\User;
use App\Services\MatchResultService;
use App\Services\Ranking\BuildCategoryRankingService;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Tests\Concerns\CreatesMatchResultWorkflow;
use Tests\TestCase;

class AdminMatchConflictResolutionTest extends TestCase
{
    use CreatesMatchResultWorkflow;
    use RefreshDatabase;

    public function test_admin_resolves_conflict_preserves_reports_and_updates_ranking(): void
    {
        [$match, $homePlayer, $awayPlayer] = $this->createSinglesResultMatch();

        $this->actingAs($homePlayer->user)
            ->postJson("/api/v1/matches/{$match->id}/submit-result", [
                'home_score' => 10,
                'away_score' => 7,
                'comment' => 'Versión local para admin',
            ])
            ->assertOk();
        $this->actingAs($awayPlayer->user)
            ->postJson("/api/v1/matches/{$match->id}/submit-result", [
                'home_score' => 6,
                'away_score' => 10,
                'comment' => 'Versión visitante para admin',
            ])
            ->assertOk();

        $admin = User::factory()->admin()->create();
        $response = $this->actingAs($admin)
            ->postJson("/api/v1/admin/matches/{$match->id}/resolve-conflict", [
                'home_score' => 10,
                'away_score' => 7,
            ]);

        $response
            ->assertOk()
            ->assertJsonPath('data.match.status', 'validated')
            ->assertJsonPath('data.match.home_score', 10)
            ->assertJsonPath('data.match.away_score', 7)
            ->assertJsonPath('data.match.winner_entry_id', $match->home_entry_id)
            ->assertJsonCount(2, 'data.reports');

        $this->assertDatabaseHas('game_matches', [
            'id' => $match->id,
            'status' => 'validated',
            'home_score' => 10,
            'away_score' => 7,
            'winner_entry_id' => $match->home_entry_id,
            'validated_by' => $admin->id,
        ]);
        $this->assertSame(
            2,
            MatchResultReport::query()
                ->where('game_match_id', $match->id)
                ->where('status', 'conflict')
                ->count()
        );
        $this->assertDatabaseHas('match_result_reports', [
            'game_match_id' => $match->id,
            'player_id' => $homePlayer->id,
            'comment' => 'Versión local para admin',
        ]);
        $this->assertDatabaseHas('match_result_reports', [
            'game_match_id' => $match->id,
            'player_id' => $awayPlayer->id,
            'comment' => 'Versión visitante para admin',
        ]);

        $category = $match->round->category;
        $ranking = app(BuildCategoryRankingService::class)->build($category);

        $this->assertSame($match->home_entry_id, $ranking[0]['entry_id']);
        $this->assertSame(1, $ranking[0]['wins']);
        $this->assertSame(3, $ranking[0]['points']);
    }

    public function test_non_admin_cannot_resolve_conflict(): void
    {
        [$match] = $this->createSinglesResultMatch(['status' => 'under_review']);
        $user = User::factory()->create();

        $this->actingAs($user)
            ->postJson("/api/v1/admin/matches/{$match->id}/resolve-conflict", [
                'home_score' => 10,
                'away_score' => 7,
            ])
            ->assertForbidden();

        $this->assertDatabaseHas('game_matches', [
            'id' => $match->id,
            'status' => 'under_review',
            'home_score' => null,
            'away_score' => null,
            'winner_entry_id' => null,
        ]);
    }

    public function test_admin_can_open_empty_conflict_list(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->get(route('admin.match-conflicts.index'))
            ->assertOk()
            ->assertSee('Conflictos de resultados')
            ->assertSee('No hay conflictos de resultados pendientes de resolución.');
    }

    public function test_conflict_list_only_shows_under_review_matches_with_both_reports(): void
    {
        [$match, $homePlayer, $awayPlayer] = $this->createSinglesConflict(
            'Comentario local visible',
            'Comentario visitante visible'
        );
        $this->nameMatchContext($match);

        [$scheduledMatch] = $this->createSinglesResultMatch();
        $scheduledMatch->round->update(['name' => 'Jornada fuera del listado']);

        $admin = User::factory()->admin()->create();
        $response = $this->actingAs($admin)->get(route('admin.match-conflicts.index'));

        $response
            ->assertOk()
            ->assertSee('Campeonato de conflicto')
            ->assertSee('Categoría de conflicto')
            ->assertSee('Jornada de conflicto')
            ->assertSee('Pista de conflicto')
            ->assertSee('Pilotari local')
            ->assertSee('Pilotari visitante')
            ->assertSee('10 - 7')
            ->assertSee('6 - 10')
            ->assertSee('Comentario local visible')
            ->assertSee('Comentario visitante visible')
            ->assertSee(route('admin.match-conflicts.show', $match))
            ->assertDontSee('Jornada fuera del listado')
            ->assertDontSee($homePlayer->user->email)
            ->assertDontSee($awayPlayer->user->email);
    }

    public function test_conflict_detail_shows_competitive_context_and_original_comments(): void
    {
        [$match, $homePlayer, $awayPlayer] = $this->createSinglesConflict(
            'Detalle local original',
            'Detalle visitante original'
        );
        $this->nameMatchContext($match);

        $admin = User::factory()->admin()->create();
        $response = $this->actingAs($admin)->get(route('admin.match-conflicts.show', $match));

        $response
            ->assertOk()
            ->assertSee('Resolver conflicto de resultado')
            ->assertSee('Campeonato de conflicto')
            ->assertSee('Categoría de conflicto')
            ->assertSee('Jornada de conflicto')
            ->assertSee('Pista de conflicto')
            ->assertSee('Reporte local')
            ->assertSee('Reporte visitante')
            ->assertSee('Detalle local original')
            ->assertSee('Detalle visitante original')
            ->assertSee('Tanteo oficial local')
            ->assertSee('Tanteo oficial visitante')
            ->assertSee(route('admin.match-conflicts.resolve', $match))
            ->assertDontSee($homePlayer->user->email)
            ->assertDontSee($awayPlayer->user->email);
    }

    public function test_web_conflict_routes_reject_normal_and_inactive_users(): void
    {
        [$match] = $this->createSinglesConflict();
        $normalUser = User::factory()->create();

        $this->actingAs($normalUser)
            ->get(route('admin.match-conflicts.index'))
            ->assertForbidden();
        $this->actingAs($normalUser)
            ->get(route('admin.match-conflicts.show', $match))
            ->assertForbidden();
        $this->actingAs($normalUser)
            ->post(route('admin.match-conflicts.resolve', $match), [
                'home_score' => 10,
                'away_score' => 7,
            ])
            ->assertForbidden();

        $inactiveAdmin = User::factory()->admin()->create(['active' => false]);

        $this->actingAs($inactiveAdmin)
            ->get(route('admin.match-conflicts.index'))
            ->assertRedirect(route('admin.login'))
            ->assertSessionHasErrors('email');

        $this->assertGuest('web');
    }

    public function test_admin_resolves_conflict_from_blade_with_traceability_and_ranking_effect(): void
    {
        [$match, $homePlayer, $awayPlayer] = $this->createSinglesConflict(
            'Original local conservado',
            'Original visitante conservado'
        );
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->post(route('admin.match-conflicts.resolve', $match), [
                'home_score' => 10,
                'away_score' => 8,
            ])
            ->assertRedirect(route('admin.match-conflicts.index'))
            ->assertSessionHas('success', 'Conflicto resuelto y resultado validado correctamente.');

        $this->assertDatabaseHas('game_matches', [
            'id' => $match->id,
            'status' => 'validated',
            'home_score' => 10,
            'away_score' => 8,
            'winner_entry_id' => $match->home_entry_id,
            'validated_by' => $admin->id,
        ]);
        $this->assertDatabaseHas('match_result_reports', [
            'game_match_id' => $match->id,
            'player_id' => $homePlayer->id,
            'home_score' => 10,
            'away_score' => 7,
            'status' => 'conflict',
            'comment' => 'Original local conservado',
        ]);
        $this->assertDatabaseHas('match_result_reports', [
            'game_match_id' => $match->id,
            'player_id' => $awayPlayer->id,
            'home_score' => 6,
            'away_score' => 10,
            'status' => 'conflict',
            'comment' => 'Original visitante conservado',
        ]);

        $ranking = app(BuildCategoryRankingService::class)->build($match->round->category);

        $this->assertSame($match->home_entry_id, $ranking[0]['entry_id']);
        $this->assertSame(1, $ranking[0]['wins']);
        $this->assertSame(3, $ranking[0]['points']);
    }

    public function test_invalid_and_tied_resolutions_leave_match_and_reports_unchanged(): void
    {
        [$match] = $this->createSinglesConflict();
        $admin = User::factory()->admin()->create();
        $reportsBefore = $this->reportSnapshot($match);

        $this->actingAs($admin)
            ->from(route('admin.match-conflicts.show', $match))
            ->post(route('admin.match-conflicts.resolve', $match), [
                'home_score' => -1,
                'away_score' => 7,
            ])
            ->assertRedirect(route('admin.match-conflicts.show', $match))
            ->assertSessionHasErrors('home_score');

        $this->actingAs($admin)
            ->from(route('admin.match-conflicts.show', $match))
            ->post(route('admin.match-conflicts.resolve', $match), [
                'home_score' => 9,
                'away_score' => 7,
            ])
            ->assertRedirect(route('admin.match-conflicts.show', $match))
            ->assertSessionHasErrors('scores');

        $this->actingAs($admin)
            ->from(route('admin.match-conflicts.show', $match))
            ->post(route('admin.match-conflicts.resolve', $match), [
                'home_score' => 10,
                'away_score' => 10,
            ])
            ->assertRedirect(route('admin.match-conflicts.show', $match))
            ->assertSessionHasErrors('scores');

        $this->assertDatabaseHas('game_matches', [
            'id' => $match->id,
            'status' => 'under_review',
            'home_score' => null,
            'away_score' => null,
            'winner_entry_id' => null,
            'validated_by' => null,
        ]);
        $this->assertSame($reportsBefore, $this->reportSnapshot($match));
    }

    public function test_non_reviewed_match_and_second_resolution_are_rejected(): void
    {
        [$scheduledMatch] = $this->createSinglesResultMatch();
        [$conflictMatch] = $this->createSinglesConflict();
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->post(route('admin.match-conflicts.resolve', $scheduledMatch), [
                'home_score' => 10,
                'away_score' => 7,
            ])
            ->assertRedirect(route('admin.match-conflicts.index'))
            ->assertSessionHas('error', 'Solo se pueden resolver partidos que estén en revisión.');

        $this->actingAs($admin)
            ->post(route('admin.match-conflicts.resolve', $conflictMatch), [
                'home_score' => 10,
                'away_score' => 8,
            ])
            ->assertRedirect(route('admin.match-conflicts.index'));

        $this->actingAs($admin)
            ->post(route('admin.match-conflicts.resolve', $conflictMatch), [
                'home_score' => 7,
                'away_score' => 10,
            ])
            ->assertRedirect(route('admin.match-conflicts.index'))
            ->assertSessionHas('error', 'Solo se pueden resolver partidos que estén en revisión.');

        $this->assertDatabaseHas('game_matches', [
            'id' => $scheduledMatch->id,
            'status' => 'scheduled',
            'home_score' => null,
            'away_score' => null,
            'winner_entry_id' => null,
        ]);
        $this->assertDatabaseHas('game_matches', [
            'id' => $conflictMatch->id,
            'status' => 'validated',
            'home_score' => 10,
            'away_score' => 8,
            'winner_entry_id' => $conflictMatch->home_entry_id,
            'validated_by' => $admin->id,
        ]);
    }

    public function test_resolution_rolls_back_if_persistence_fails_after_match_update(): void
    {
        [$match] = $this->createSinglesConflict();
        $admin = User::factory()->admin()->create();
        $reportsBefore = $this->reportSnapshot($match);

        DB::listen(static function (QueryExecuted $query): void {
            if (str_starts_with($query->sql, 'update `game_matches`')
                && str_contains($query->sql, '`home_score`')) {
                throw new RuntimeException('Fallo de persistencia simulado.');
            }
        });

        try {
            app(MatchResultService::class)->resolveConflict($match, 10, 8, $admin);
            $this->fail('La resolución debía fallar después de actualizar el partido.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Fallo de persistencia simulado.', $exception->getMessage());
        }

        $this->assertDatabaseHas('game_matches', [
            'id' => $match->id,
            'status' => 'under_review',
            'home_score' => null,
            'away_score' => null,
            'winner_entry_id' => null,
            'validated_by' => null,
        ]);
        $this->assertSame($reportsBefore, $this->reportSnapshot($match));
    }

    public function test_admin_resolves_doubles_conflict_with_target_twelve(): void
    {
        [$match, $homePlayers, $awayPlayers] = $this->createDoublesResultMatch();

        $this->actingAs($homePlayers[0]->user)
            ->postJson("/api/v1/matches/{$match->id}/submit-result", [
                'home_score' => 12,
                'away_score' => 8,
                'comment' => 'Versión del equipo local',
            ])
            ->assertOk();
        $this->actingAs($awayPlayers[0]->user)
            ->postJson("/api/v1/matches/{$match->id}/submit-result", [
                'home_score' => 7,
                'away_score' => 12,
                'comment' => 'Versión del equipo visitante',
            ])
            ->assertOk();

        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->post(route('admin.match-conflicts.resolve', $match), [
                'home_score' => 12,
                'away_score' => 9,
            ])
            ->assertRedirect(route('admin.match-conflicts.index'));

        $this->assertDatabaseHas('game_matches', [
            'id' => $match->id,
            'status' => 'validated',
            'home_score' => 12,
            'away_score' => 9,
            'winner_entry_id' => $match->home_entry_id,
            'validated_by' => $admin->id,
        ]);
        $this->assertSame(
            2,
            MatchResultReport::query()
                ->where('game_match_id', $match->id)
                ->where('status', 'conflict')
                ->count()
        );
    }

    /**
     * @return array{GameMatch, Player, Player}
     */
    private function createSinglesConflict(
        string $homeComment = 'Versión local para revisión',
        string $awayComment = 'Versión visitante para revisión'
    ): array {
        [$match, $homePlayer, $awayPlayer] = $this->createSinglesResultMatch();

        $this->actingAs($homePlayer->user)
            ->postJson("/api/v1/matches/{$match->id}/submit-result", [
                'home_score' => 10,
                'away_score' => 7,
                'comment' => $homeComment,
            ])
            ->assertOk();
        $this->actingAs($awayPlayer->user)
            ->postJson("/api/v1/matches/{$match->id}/submit-result", [
                'home_score' => 6,
                'away_score' => 10,
                'comment' => $awayComment,
            ])
            ->assertOk();

        return [$match->fresh(), $homePlayer, $awayPlayer];
    }

    private function nameMatchContext(GameMatch $match): void
    {
        $match->load('round.category.championship', 'venue', 'homeEntry.player', 'awayEntry.player');
        $match->round->category->championship->update(['name' => 'Campeonato de conflicto']);
        $match->round->category->update(['name' => 'Categoría de conflicto']);
        $match->round->update(['name' => 'Jornada de conflicto']);
        $match->venue->update(['name' => 'Pista de conflicto']);
        $match->homeEntry->player->update(['nickname' => 'Pilotari local']);
        $match->awayEntry->player->update(['nickname' => 'Pilotari visitante']);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function reportSnapshot(GameMatch $match): array
    {
        return MatchResultReport::query()
            ->where('game_match_id', $match->id)
            ->orderBy('id')
            ->get([
                'id',
                'game_match_id',
                'user_id',
                'player_id',
                'side',
                'home_score',
                'away_score',
                'status',
                'comment',
                'created_at',
                'updated_at',
            ])
            ->map(fn (MatchResultReport $report): array => $report->getRawOriginal())
            ->all();
    }
}
