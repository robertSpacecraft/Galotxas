<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\CategoryEntry;
use App\Models\Championship;
use App\Models\GameMatch;
use App\Models\MatchResultReport;
use App\Models\Player;
use App\Models\Round;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MatchWorkflowSecurityTest extends TestCase
{
    use RefreshDatabase;

    public function test_workflow_for_user_without_player_profile_is_limited_and_private_data_is_hidden(): void
    {
        [$match, $homePlayer] = $this->createSinglesMatch();
        $this->createReport($match, $homePlayer, 'home', 'Comentario privado del local');
        $userWithoutPlayer = User::factory()->create(['email' => 'without-player@example.com']);

        $response = $this->actingAs($userWithoutPlayer)
            ->getJson("/api/v1/matches/{$match->id}/workflow");

        $response
            ->assertOk()
            ->assertJsonPath('data.match.id', $match->id)
            ->assertJsonPath('data.workflow.participates', false)
            ->assertJsonPath('data.workflow.user_side', null)
            ->assertJsonPath('data.workflow.can_report', false)
            ->assertJsonPath('data.workflow.my_report', null)
            ->assertJsonPath('data.workflow.same_side_report_by_teammate', null)
            ->assertJsonPath('data.workflow.opposite_report', null)
            ->assertJsonMissingPath('data.match.result_reports')
            ->assertJsonMissingPath('data.match.submitted_by')
            ->assertJsonMissingPath('data.match.validated_by');

        $content = $response->getContent();
        $this->assertStringNotContainsString('Comentario privado del local', $content);
        $this->assertStringNotContainsString($homePlayer->user->email, $content);
    }

    public function test_workflow_for_non_participant_is_limited_and_private_data_is_hidden(): void
    {
        [$match, $homePlayer] = $this->createSinglesMatch();
        $this->createReport($match, $homePlayer, 'home', 'Comentario privado no visible');
        $outsider = $this->createPlayer('outsider@example.com');

        $response = $this->actingAs($outsider->user)
            ->getJson("/api/v1/matches/{$match->id}/workflow");

        $response
            ->assertOk()
            ->assertJsonPath('data.workflow.participates', false)
            ->assertJsonPath('data.workflow.can_report', false)
            ->assertJsonPath('data.workflow.my_report', null)
            ->assertJsonPath('data.workflow.opposite_report', null)
            ->assertJsonMissingPath('data.match.result_reports')
            ->assertJsonMissingPath('data.match.created_at')
            ->assertJsonMissingPath('data.match.updated_at');

        $content = $response->getContent();
        $this->assertStringNotContainsString('Comentario privado no visible', $content);
        $this->assertStringNotContainsString($homePlayer->user->email, $content);
    }

    public function test_participant_workflow_exposes_only_safe_reports_required_by_react(): void
    {
        [$match, $homePlayer, $awayPlayer] = $this->createSinglesMatch();
        $this->createReport($match, $homePlayer, 'home', 'Mi observación');
        $this->createReport($match, $awayPlayer, 'away', 'Observación rival');
        $match->update([
            'status' => 'submitted',
            'home_score' => 10,
            'away_score' => 7,
            'winner_entry_id' => $match->home_entry_id,
        ]);

        $response = $this->actingAs($homePlayer->user)
            ->getJson("/api/v1/matches/{$match->id}/workflow");

        $response
            ->assertOk()
            ->assertJsonPath('data.workflow.participates', true)
            ->assertJsonPath('data.workflow.user_side', 'home')
            ->assertJsonPath('data.workflow.can_report', false)
            ->assertJsonPath('data.workflow.blocked_reason', 'already_reported_by_you')
            ->assertJsonPath('data.workflow.match_status', 'submitted')
            ->assertJsonPath('data.match.home_score', null)
            ->assertJsonPath('data.match.away_score', null)
            ->assertJsonPath('data.match.winner_entry', null)
            ->assertJsonPath('data.workflow.my_report.side', 'home')
            ->assertJsonPath('data.workflow.my_report.comment', 'Mi observación')
            ->assertJsonPath('data.workflow.opposite_report.side', 'away')
            ->assertJsonPath('data.workflow.opposite_report.comment', 'Observación rival')
            ->assertJsonMissingPath('data.match.result_reports')
            ->assertJsonMissingPath('data.match.winner_entry_id')
            ->assertJsonMissingPath('data.match.submitted_by')
            ->assertJsonMissingPath('data.workflow.my_report.user')
            ->assertJsonMissingPath('data.workflow.my_report.user_id')
            ->assertJsonMissingPath('data.workflow.my_report.player_id')
            ->assertJsonMissingPath('data.workflow.opposite_report.user');

        $content = $response->getContent();
        $this->assertStringNotContainsString($homePlayer->user->email, $content);
        $this->assertStringNotContainsString($awayPlayer->user->email, $content);
    }

    public function test_participant_can_submit_result_and_response_uses_safe_resources(): void
    {
        [$match, $homePlayer] = $this->createSinglesMatch();

        $response = $this->actingAs($homePlayer->user)
            ->postJson("/api/v1/matches/{$match->id}/submit-result", [
                'home_score' => 10,
                'away_score' => 7,
                'comment' => 'Resultado comunicado por el local',
            ]);

        $response
            ->assertOk()
            ->assertJsonPath('data.match.status', 'submitted')
            ->assertJsonPath('data.report.side', 'home')
            ->assertJsonPath('data.report.comment', 'Resultado comunicado por el local')
            ->assertJsonMissingPath('data.match.result_reports')
            ->assertJsonMissingPath('data.match.submitted_by')
            ->assertJsonMissingPath('data.report.user')
            ->assertJsonMissingPath('data.report.user_id');

        $this->assertDatabaseHas('match_result_reports', [
            'game_match_id' => $match->id,
            'player_id' => $homePlayer->id,
            'side' => 'home',
        ]);
        $this->assertStringNotContainsString($homePlayer->user->email, $response->getContent());
    }

    public function test_non_participant_cannot_submit_result(): void
    {
        [$match] = $this->createSinglesMatch();
        $outsider = $this->createPlayer('submit-outsider@example.com');

        $this->actingAs($outsider->user)
            ->postJson("/api/v1/matches/{$match->id}/submit-result", [
                'home_score' => 10,
                'away_score' => 7,
            ])
            ->assertUnprocessable()
            ->assertJsonPath('message', 'El jugador no participa en este partido.');

        $this->assertDatabaseMissing('match_result_reports', [
            'game_match_id' => $match->id,
            'player_id' => $outsider->id,
        ]);
    }

    public function test_opposite_participant_can_confirm_result_and_response_remains_safe(): void
    {
        [$match, $homePlayer, $awayPlayer] = $this->createSinglesMatch();

        $this->actingAs($homePlayer->user)
            ->postJson("/api/v1/matches/{$match->id}/submit-result", [
                'home_score' => 10,
                'away_score' => 7,
                'comment' => 'Primer reporte',
            ])
            ->assertOk();

        $response = $this->actingAs($awayPlayer->user)
            ->postJson("/api/v1/matches/{$match->id}/confirm-result");

        $response
            ->assertOk()
            ->assertJsonPath('data.match.status', 'validated')
            ->assertJsonPath('data.match.home_score', 10)
            ->assertJsonPath('data.match.away_score', 7)
            ->assertJsonPath('data.report.side', 'away')
            ->assertJsonMissingPath('data.match.result_reports')
            ->assertJsonMissingPath('data.report.user')
            ->assertJsonMissingPath('data.opposite_report.user');

        $this->assertDatabaseHas('game_matches', [
            'id' => $match->id,
            'status' => 'validated',
            'home_score' => 10,
            'away_score' => 7,
        ]);
        $this->assertStringNotContainsString($homePlayer->user->email, $response->getContent());
        $this->assertStringNotContainsString($awayPlayer->user->email, $response->getContent());
    }

    public function test_non_participant_cannot_confirm_result(): void
    {
        [$match, $homePlayer] = $this->createSinglesMatch();
        $outsider = $this->createPlayer('confirm-outsider@example.com');

        $this->actingAs($homePlayer->user)
            ->postJson("/api/v1/matches/{$match->id}/submit-result", [
                'home_score' => 10,
                'away_score' => 7,
            ])
            ->assertOk();

        $this->actingAs($outsider->user)
            ->postJson("/api/v1/matches/{$match->id}/confirm-result")
            ->assertUnprocessable()
            ->assertJsonPath('message', 'El jugador no participa en este partido.');

        $this->assertDatabaseHas('game_matches', [
            'id' => $match->id,
            'status' => 'submitted',
            'home_score' => null,
            'away_score' => null,
        ]);
        $this->assertDatabaseMissing('match_result_reports', [
            'game_match_id' => $match->id,
            'player_id' => $outsider->id,
        ]);
    }

    public function test_doubles_teammate_receives_safe_same_side_report_without_email(): void
    {
        $championship = Championship::factory()->create(['type' => 'doubles']);
        $category = Category::factory()->create(['championship_id' => $championship->id]);
        $round = Round::factory()->create([
            'category_id' => $category->id,
            'type' => 'league',
            'phase' => 'league',
            'stage' => 'matchday',
        ]);
        $homeReporter = $this->createPlayer('doubles-reporter@example.com');
        $homeTeammate = $this->createPlayer('doubles-teammate@example.com');
        $awayPlayerOne = $this->createPlayer('doubles-away-one@example.com');
        $awayPlayerTwo = $this->createPlayer('doubles-away-two@example.com');
        $homeTeam = Team::factory()->create(['category_id' => $category->id]);
        $awayTeam = Team::factory()->create(['category_id' => $category->id]);

        $homeTeam->players()->attach($homeReporter->id, ['role_in_team' => 'front']);
        $homeTeam->players()->attach($homeTeammate->id, ['role_in_team' => 'back']);
        $awayTeam->players()->attach($awayPlayerOne->id, ['role_in_team' => 'front']);
        $awayTeam->players()->attach($awayPlayerTwo->id, ['role_in_team' => 'back']);

        $homeEntry = CategoryEntry::factory()->teamEntry()->create([
            'category_id' => $category->id,
            'team_id' => $homeTeam->id,
            'status' => 'approved',
        ]);
        $awayEntry = CategoryEntry::factory()->teamEntry()->create([
            'category_id' => $category->id,
            'team_id' => $awayTeam->id,
            'status' => 'approved',
        ]);
        $match = GameMatch::factory()->create([
            'round_id' => $round->id,
            'home_entry_id' => $homeEntry->id,
            'away_entry_id' => $awayEntry->id,
            'status' => 'scheduled',
        ]);
        $this->createReport($match, $homeReporter, 'home', 'Reporte de mi pareja', 12, 8);

        $response = $this->actingAs($homeTeammate->user)
            ->getJson("/api/v1/matches/{$match->id}/workflow");

        $response
            ->assertOk()
            ->assertJsonPath('data.workflow.participates', true)
            ->assertJsonPath('data.workflow.user_side', 'home')
            ->assertJsonPath('data.workflow.can_report', false)
            ->assertJsonPath('data.workflow.blocked_reason', 'already_reported_by_teammate')
            ->assertJsonPath('data.workflow.same_side_report_by_teammate.side', 'home')
            ->assertJsonPath('data.workflow.same_side_report_by_teammate.comment', 'Reporte de mi pareja')
            ->assertJsonMissingPath('data.workflow.same_side_report_by_teammate.user');

        $this->assertStringNotContainsString($homeReporter->user->email, $response->getContent());
    }

    /**
     * @return array{GameMatch, Player, Player}
     */
    private function createSinglesMatch(): array
    {
        $championship = Championship::factory()->create(['type' => 'singles']);
        $category = Category::factory()->create(['championship_id' => $championship->id]);
        $round = Round::factory()->create([
            'category_id' => $category->id,
            'type' => 'league',
            'phase' => 'league',
            'stage' => 'matchday',
        ]);
        $homePlayer = $this->createPlayer('home-participant@example.com');
        $awayPlayer = $this->createPlayer('away-participant@example.com');
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
        $match = GameMatch::factory()->create([
            'round_id' => $round->id,
            'home_entry_id' => $homeEntry->id,
            'away_entry_id' => $awayEntry->id,
            'status' => 'scheduled',
            'home_score' => null,
            'away_score' => null,
            'winner_entry_id' => null,
            'submitted_by' => null,
            'validated_by' => null,
        ]);

        return [$match, $homePlayer, $awayPlayer];
    }

    private function createPlayer(string $email): Player
    {
        $user = User::factory()->create(['email' => $email]);

        return Player::factory()->create([
            'user_id' => $user->id,
            'active' => true,
        ])->load('user');
    }

    private function createReport(
        GameMatch $match,
        Player $player,
        string $side,
        string $comment,
        int $homeScore = 10,
        int $awayScore = 7
    ): MatchResultReport {
        return MatchResultReport::query()->create([
            'game_match_id' => $match->id,
            'user_id' => $player->user_id,
            'player_id' => $player->id,
            'side' => $side,
            'home_score' => $homeScore,
            'away_score' => $awayScore,
            'status' => 'submitted',
            'comment' => $comment,
        ]);
    }
}
