<?php

namespace Tests\Feature;

use App\Models\GameMatch;
use App\Models\MatchResultReport;
use App\Models\User;
use App\Services\MatchResultReportService;
use App\Services\MatchResultService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\Concerns\CreatesMatchResultWorkflow;
use Tests\TestCase;

class MatchResultWorkflowTest extends TestCase
{
    use CreatesMatchResultWorkflow;
    use RefreshDatabase;

    public function test_home_and_away_participants_can_make_the_first_submission(): void
    {
        [$homeMatch, $homePlayer] = $this->createSinglesResultMatch();

        $homeResponse = $this->actingAs($homePlayer->user)
            ->postJson("/api/v1/matches/{$homeMatch->id}/submit-result", [
                'home_score' => 10,
                'away_score' => 7,
                'comment' => 'Primer envío local',
            ]);

        $homeResponse
            ->assertOk()
            ->assertJsonPath('data.match.status', 'submitted')
            ->assertJsonPath('data.match.home_score', null)
            ->assertJsonPath('data.match.away_score', null)
            ->assertJsonPath('data.match.winner_entry', null)
            ->assertJsonPath('data.report.side', 'home')
            ->assertJsonPath('data.report.status', 'submitted')
            ->assertJsonPath('data.report.comment', 'Primer envío local');

        $this->assertDatabaseHas('game_matches', [
            'id' => $homeMatch->id,
            'status' => 'submitted',
            'home_score' => null,
            'away_score' => null,
            'winner_entry_id' => null,
        ]);

        [$awayMatch, , $awayPlayer] = $this->createSinglesResultMatch();

        $awayResponse = $this->actingAs($awayPlayer->user)
            ->postJson("/api/v1/matches/{$awayMatch->id}/submit-result", [
                'home_score' => 6,
                'away_score' => 10,
            ]);

        $awayResponse
            ->assertOk()
            ->assertJsonPath('data.match.status', 'submitted')
            ->assertJsonPath('data.report.side', 'away')
            ->assertJsonPath('data.report.comment', null);
    }

    public function test_submission_validates_payload_and_sport_score_rules(): void
    {
        [$match, $homePlayer] = $this->createSinglesResultMatch();
        $endpoint = "/api/v1/matches/{$match->id}/submit-result";

        $this->actingAs($homePlayer->user)
            ->postJson($endpoint, ['home_score' => 10.5, 'away_score' => 7])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('home_score');

        $this->actingAs($homePlayer->user)
            ->postJson($endpoint, ['home_score' => 10, 'away_score' => -1])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('away_score');

        $this->actingAs($homePlayer->user)
            ->postJson($endpoint, [
                'home_score' => 10,
                'away_score' => 7,
                'comment' => str_repeat('a', 2001),
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('comment');

        $this->actingAs($homePlayer->user)
            ->postJson($endpoint, ['home_score' => 10, 'away_score' => 10])
            ->assertUnprocessable()
            ->assertJsonPath('message', 'No puede haber empate en Galotxas.');

        $this->actingAs($homePlayer->user)
            ->postJson($endpoint, ['home_score' => 9, 'away_score' => 7])
            ->assertUnprocessable()
            ->assertJsonPath('message', 'Uno de los dos equipos/jugadores debe alcanzar 10 juegos.');

        $this->assertDatabaseCount('match_result_reports', 0);
    }

    public function test_user_without_profile_and_outsider_cannot_submit(): void
    {
        [$match] = $this->createSinglesResultMatch();
        $userWithoutPlayer = User::factory()->create();
        $outsider = $this->createResultPlayer();
        $payload = ['home_score' => 10, 'away_score' => 7];

        $this->actingAs($userWithoutPlayer)
            ->postJson("/api/v1/matches/{$match->id}/submit-result", $payload)
            ->assertUnprocessable()
            ->assertJsonPath('message', 'El usuario autenticado no tiene un perfil de jugador asociado.');

        $this->actingAs($outsider->user)
            ->postJson("/api/v1/matches/{$match->id}/submit-result", $payload)
            ->assertUnprocessable()
            ->assertJsonPath('message', 'El jugador no participa en este partido.');

        $this->assertDatabaseCount('match_result_reports', 0);
    }

    public function test_same_player_cannot_repeat_or_overwrite_a_submission(): void
    {
        [$match, $homePlayer] = $this->createSinglesResultMatch();
        $endpoint = "/api/v1/matches/{$match->id}/submit-result";

        $this->actingAs($homePlayer->user)
            ->postJson($endpoint, [
                'home_score' => 10,
                'away_score' => 7,
                'comment' => 'Reporte original',
            ])
            ->assertOk();

        $this->actingAs($homePlayer->user)
            ->postJson($endpoint, [
                'home_score' => 10,
                'away_score' => 6,
                'comment' => 'Intento de sobrescritura',
            ])
            ->assertUnprocessable()
            ->assertJsonPath('message', 'Ya has enviado un reporte para este partido.');

        $this->assertDatabaseCount('match_result_reports', 1);
        $this->assertDatabaseHas('match_result_reports', [
            'game_match_id' => $match->id,
            'player_id' => $homePlayer->id,
            'home_score' => 10,
            'away_score' => 7,
            'comment' => 'Reporte original',
        ]);
    }

    public function test_only_opposite_side_can_confirm_and_confirmation_validates_comment(): void
    {
        [$match, $homePlayer, $awayPlayer] = $this->createSinglesResultMatch();

        $this->actingAs($homePlayer->user)
            ->postJson("/api/v1/matches/{$match->id}/submit-result", [
                'home_score' => 10,
                'away_score' => 7,
            ])
            ->assertOk();

        $this->actingAs($homePlayer->user)
            ->postJson("/api/v1/matches/{$match->id}/confirm-result")
            ->assertUnprocessable()
            ->assertJsonPath('message', 'Ya has enviado un reporte para este partido.');

        $this->actingAs($awayPlayer->user)
            ->postJson("/api/v1/matches/{$match->id}/confirm-result", [
                'comment' => str_repeat('a', 2001),
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('comment');

        $this->actingAs($awayPlayer->user)
            ->postJson("/api/v1/matches/{$match->id}/confirm-result", [
                'comment' => ['no válido'],
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('comment');

        $this->assertDatabaseCount('match_result_reports', 1);
        $this->assertDatabaseHas('game_matches', [
            'id' => $match->id,
            'status' => 'submitted',
            'home_score' => null,
            'away_score' => null,
        ]);
    }

    public function test_opposite_confirmation_validates_match_reports_and_winner_atomically(): void
    {
        [$match, $homePlayer, $awayPlayer] = $this->createSinglesResultMatch();

        $this->actingAs($homePlayer->user)
            ->postJson("/api/v1/matches/{$match->id}/submit-result", [
                'home_score' => 10,
                'away_score' => 7,
            ])
            ->assertOk();

        $response = $this->actingAs($awayPlayer->user)
            ->postJson("/api/v1/matches/{$match->id}/confirm-result", [
                'comment' => 'Confirmado por el rival',
            ]);

        $response
            ->assertOk()
            ->assertJsonPath('message', 'Resultado validado correctamente.')
            ->assertJsonPath('data.match.status', 'validated')
            ->assertJsonPath('data.match.home_score', 10)
            ->assertJsonPath('data.match.away_score', 7)
            ->assertJsonPath('data.match.winner_entry.id', $match->home_entry_id)
            ->assertJsonPath('data.report.side', 'away')
            ->assertJsonPath('data.report.status', 'validated')
            ->assertJsonPath('data.opposite_report.status', 'validated')
            ->assertJsonMissingPath('data.report.user')
            ->assertJsonMissingPath('data.match.submitted_by');

        $this->assertDatabaseHas('game_matches', [
            'id' => $match->id,
            'status' => 'validated',
            'home_score' => 10,
            'away_score' => 7,
            'winner_entry_id' => $match->home_entry_id,
            'submitted_by' => $homePlayer->user_id,
            'validated_by' => $awayPlayer->user_id,
        ]);
        $this->assertSame(2, MatchResultReport::query()->where('game_match_id', $match->id)->count());
        $this->assertSame(
            2,
            MatchResultReport::query()
                ->where('game_match_id', $match->id)
                ->where('status', 'validated')
                ->count()
        );
    }

    public function test_different_opposite_report_creates_conflict_and_blocks_further_reports(): void
    {
        [$match, $homePlayer, $awayPlayer] = $this->createSinglesResultMatch();

        $this->actingAs($homePlayer->user)
            ->postJson("/api/v1/matches/{$match->id}/submit-result", [
                'home_score' => 10,
                'away_score' => 7,
                'comment' => 'Versión local',
            ])
            ->assertOk();

        $response = $this->actingAs($awayPlayer->user)
            ->postJson("/api/v1/matches/{$match->id}/submit-result", [
                'home_score' => 6,
                'away_score' => 10,
                'comment' => 'Versión visitante',
            ]);

        $response
            ->assertOk()
            ->assertJsonPath('message', 'Se ha detectado una discrepancia. El partido queda en revisión.')
            ->assertJsonPath('data.match.status', 'under_review')
            ->assertJsonPath('data.match.home_score', null)
            ->assertJsonPath('data.match.away_score', null)
            ->assertJsonPath('data.match.winner_entry', null)
            ->assertJsonPath('data.report.status', 'conflict')
            ->assertJsonPath('data.opposite_report.status', 'conflict');

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
            'comment' => 'Versión local',
        ]);
        $this->assertDatabaseHas('match_result_reports', [
            'game_match_id' => $match->id,
            'player_id' => $awayPlayer->id,
            'comment' => 'Versión visitante',
        ]);

        $this->actingAs($homePlayer->user)
            ->postJson("/api/v1/matches/{$match->id}/submit-result", [
                'home_score' => 10,
                'away_score' => 7,
            ])
            ->assertUnprocessable()
            ->assertJsonPath('message', 'Este partido no admite nuevos reportes.');

        $this->assertDatabaseHas('game_matches', [
            'id' => $match->id,
            'status' => 'under_review',
            'home_score' => null,
            'away_score' => null,
            'winner_entry_id' => null,
        ]);
    }

    public function test_closed_states_reject_new_reports_and_confirmations(): void
    {
        foreach (['validated', 'cancelled', 'postponed', 'under_review'] as $status) {
            [$match, $homePlayer, $awayPlayer] = $this->createSinglesResultMatch();
            $this->createSubmittedResultReport($match, $homePlayer, 'home', 10, 7);
            $match->update(['status' => $status]);

            $this->actingAs($awayPlayer->user)
                ->postJson("/api/v1/matches/{$match->id}/submit-result", [
                    'home_score' => 10,
                    'away_score' => 7,
                ])
                ->assertUnprocessable()
                ->assertJsonPath('message', 'Este partido no admite nuevos reportes.');

            $this->actingAs($awayPlayer->user)
                ->postJson("/api/v1/matches/{$match->id}/confirm-result")
                ->assertUnprocessable()
                ->assertJsonPath('message', 'Este partido no admite nuevos reportes.');

            $this->assertSame(1, MatchResultReport::query()->where('game_match_id', $match->id)->count());
            $this->assertSame($status, $match->fresh()->status->value);
        }
    }

    public function test_confirmation_rolls_back_report_and_status_changes_when_winner_resolution_fails(): void
    {
        [$match, $homePlayer, $awayPlayer] = $this->createSinglesResultMatch();
        app(MatchResultReportService::class)->submitReport($match, $homePlayer->user, 10, 7);

        $failingResultService = new class extends MatchResultService
        {
            public function resolveWinnerEntryId(GameMatch $match, int $homeScore, int $awayScore): int
            {
                throw new RuntimeException('Fallo forzado al resolver ganador.');
            }
        };
        $service = new MatchResultReportService($failingResultService);

        try {
            $service->submitReport($match->fresh(), $awayPlayer->user, 10, 7);
            $this->fail('La confirmación debía fallar.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Fallo forzado al resolver ganador.', $exception->getMessage());
        }

        $this->assertDatabaseHas('game_matches', [
            'id' => $match->id,
            'status' => 'submitted',
            'home_score' => null,
            'away_score' => null,
            'winner_entry_id' => null,
            'validated_by' => null,
        ]);
        $this->assertDatabaseCount('match_result_reports', 1);
        $this->assertDatabaseHas('match_result_reports', [
            'game_match_id' => $match->id,
            'player_id' => $homePlayer->id,
            'status' => 'submitted',
        ]);
    }
}
