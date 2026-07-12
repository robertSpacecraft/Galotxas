<?php

namespace Tests\Feature;

use App\Models\MatchResultReport;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesMatchResultWorkflow;
use Tests\TestCase;

class DoublesMatchResultWorkflowTest extends TestCase
{
    use CreatesMatchResultWorkflow;
    use RefreshDatabase;

    public function test_any_team_member_can_report_but_only_one_report_is_allowed_per_side(): void
    {
        [$match, $homePlayers, $awayPlayers] = $this->createDoublesResultMatch();

        $this->actingAs($homePlayers[1]->user)
            ->postJson("/api/v1/matches/{$match->id}/submit-result", [
                'home_score' => 12,
                'away_score' => 8,
                'comment' => 'Enviado por la pareja local',
            ])
            ->assertOk()
            ->assertJsonPath('data.report.side', 'home');

        $this->actingAs($homePlayers[0]->user)
            ->postJson("/api/v1/matches/{$match->id}/submit-result", [
                'home_score' => 12,
                'away_score' => 7,
            ])
            ->assertUnprocessable()
            ->assertJsonPath('message', 'Tu lado ya ha enviado un reporte para este partido.');

        $this->actingAs($homePlayers[0]->user)
            ->postJson("/api/v1/matches/{$match->id}/confirm-result")
            ->assertUnprocessable()
            ->assertJsonPath('message', 'Tu lado ya ha enviado un reporte para este partido.');

        $this->assertSame(1, MatchResultReport::query()->where('game_match_id', $match->id)->count());

        $response = $this->actingAs($awayPlayers[0]->user)
            ->postJson("/api/v1/matches/{$match->id}/confirm-result");

        $response
            ->assertOk()
            ->assertJsonPath('data.match.status', 'validated')
            ->assertJsonPath('data.match.winner_entry.id', $match->home_entry_id)
            ->assertJsonPath('data.match.winner_entry.entry_type', 'team')
            ->assertJsonPath('data.report.side', 'away')
            ->assertJsonMissingPath('data.match.home_entry.team.players.0.email')
            ->assertJsonMissingPath('data.report.user');

        $this->assertDatabaseHas('game_matches', [
            'id' => $match->id,
            'status' => 'validated',
            'home_score' => 12,
            'away_score' => 8,
            'winner_entry_id' => $match->home_entry_id,
        ]);
        $this->assertSame(
            2,
            MatchResultReport::query()
                ->where('game_match_id', $match->id)
                ->where('status', 'validated')
                ->count()
        );

        foreach ([...$homePlayers, ...$awayPlayers] as $player) {
            $this->assertStringNotContainsString($player->user->email, $response->getContent());
        }
    }

    public function test_doubles_require_target_score_twelve(): void
    {
        [$match, $homePlayers] = $this->createDoublesResultMatch();

        $this->actingAs($homePlayers[0]->user)
            ->postJson("/api/v1/matches/{$match->id}/submit-result", [
                'home_score' => 10,
                'away_score' => 8,
            ])
            ->assertUnprocessable()
            ->assertJsonPath('message', 'Uno de los dos equipos/jugadores debe alcanzar 12 juegos.');

        $this->assertDatabaseCount('match_result_reports', 0);
    }
}
