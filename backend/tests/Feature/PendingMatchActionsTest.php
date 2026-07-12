<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesMatchResultWorkflow;
use Tests\TestCase;

class PendingMatchActionsTest extends TestCase
{
    use CreatesMatchResultWorkflow;
    use RefreshDatabase;

    public function test_endpoint_requires_authentication_and_returns_empty_collection_without_player_or_matches(): void
    {
        $this->getJson('/api/v1/me/matches/pending-actions')
            ->assertUnauthorized();

        $userWithoutPlayer = User::factory()->create(['active' => true]);

        $this->actingAs($userWithoutPlayer)
            ->getJson('/api/v1/me/matches/pending-actions')
            ->assertOk()
            ->assertExactJson([
                'message' => null,
                'data' => [],
            ]);

        $playerWithoutMatches = $this->createResultPlayer();

        $this->actingAs($playerWithoutMatches->user)
            ->getJson('/api/v1/me/matches/pending-actions')
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    public function test_scheduled_match_returns_one_safe_submit_action_only_to_its_players(): void
    {
        [$match, $homePlayer, $awayPlayer] = $this->createSinglesResultMatch();
        $outsider = $this->createResultPlayer();

        foreach ([$homePlayer, $awayPlayer] as $participant) {
            $response = $this->actingAs($participant->user)
                ->getJson('/api/v1/me/matches/pending-actions');

            $response
                ->assertOk()
                ->assertJsonCount(1, 'data')
                ->assertJsonPath('data.0.type', 'submit_result')
                ->assertJsonPath('data.0.match.id', $match->id)
                ->assertJsonPath('data.0.match.status', 'scheduled')
                ->assertJsonMissingPath('data.0.match.submitted_by')
                ->assertJsonMissingPath('data.0.match.validated_by')
                ->assertJsonMissingPath('data.0.match.result_reports')
                ->assertJsonMissingPath('data.0.match.home_entry.player_id')
                ->assertJsonMissingPath('data.0.match.away_entry.player_id')
                ->assertJsonMissingPath('data.0.match.created_at')
                ->assertJsonMissingPath('data.0.match.updated_at');

            $this->assertStringNotContainsString($homePlayer->user->email, $response->getContent());
            $this->assertStringNotContainsString($awayPlayer->user->email, $response->getContent());
        }

        $this->actingAs($outsider->user)
            ->getJson('/api/v1/me/matches/pending-actions')
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    public function test_opposite_report_creates_confirm_action_but_same_side_gets_no_second_action(): void
    {
        [$match, $homePlayer, $awayPlayer] = $this->createSinglesResultMatch([
            'status' => 'submitted',
        ]);
        $this->createSubmittedResultReport($match, $awayPlayer, 'away', 7, 10, 'Reporte visitante');

        $this->actingAs($homePlayer->user)
            ->getJson('/api/v1/me/matches/pending-actions')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.type', 'confirm_result')
            ->assertJsonPath('data.0.match.id', $match->id)
            ->assertJsonMissingPath('data.0.match.result_reports');

        $sameSideResponse = $this->actingAs($awayPlayer->user)
            ->getJson('/api/v1/me/matches/pending-actions');

        $sameSideResponse
            ->assertOk()
            ->assertJsonCount(0, 'data');

        $this->assertStringNotContainsString('Reporte visitante', $sameSideResponse->getContent());
    }

    public function test_closed_matches_are_omitted_and_under_review_is_informational(): void
    {
        [, $player] = $this->createSinglesResultMatch(['status' => 'validated']);
        $this->createSinglesResultMatch(['status' => 'cancelled']);
        $this->createSinglesResultMatch(['status' => 'postponed']);
        [$reviewMatch, $reviewHome, $reviewAway] = $this->createSinglesResultMatch([
            'status' => 'under_review',
        ]);
        $homeReport = $this->createSubmittedResultReport(
            $reviewMatch,
            $reviewHome,
            'home',
            10,
            7,
            'Comentario interno local'
        );
        $awayReport = $this->createSubmittedResultReport(
            $reviewMatch,
            $reviewAway,
            'away',
            8,
            10,
            'Comentario interno visitante'
        );
        $homeReport->update(['status' => 'conflict']);
        $awayReport->update(['status' => 'conflict']);

        $this->actingAs($player->user)
            ->getJson('/api/v1/me/matches/pending-actions')
            ->assertOk()
            ->assertJsonCount(0, 'data');

        foreach ([$reviewHome, $reviewAway] as $reviewPlayer) {
            $response = $this->actingAs($reviewPlayer->user)
                ->getJson('/api/v1/me/matches/pending-actions');

            $response
                ->assertOk()
                ->assertJsonCount(1, 'data')
                ->assertJsonPath('data.0.type', 'under_review')
                ->assertJsonPath('data.0.match.id', $reviewMatch->id)
                ->assertJsonPath('data.0.match.status', 'under_review')
                ->assertJsonMissingPath('data.0.match.result_reports');

            $this->assertStringNotContainsString('Comentario interno', $response->getContent());
        }
    }

    public function test_each_closed_status_is_excluded_for_its_own_participant(): void
    {
        foreach (['validated', 'cancelled', 'postponed'] as $status) {
            [, $homePlayer] = $this->createSinglesResultMatch(['status' => $status]);

            $this->actingAs($homePlayer->user)
                ->getJson('/api/v1/me/matches/pending-actions')
                ->assertOk()
                ->assertJsonCount(0, 'data');
        }
    }

    public function test_doubles_members_share_the_side_action_without_duplicates(): void
    {
        [$match, $homePlayers, $awayPlayers] = $this->createDoublesResultMatch();

        foreach ([...$homePlayers, ...$awayPlayers] as $player) {
            $this->actingAs($player->user)
                ->getJson('/api/v1/me/matches/pending-actions')
                ->assertOk()
                ->assertJsonCount(1, 'data')
                ->assertJsonPath('data.0.type', 'submit_result')
                ->assertJsonPath('data.0.match.id', $match->id)
                ->assertJsonMissingPath('data.0.match.home_entry.team.players.0.email');
        }

        $match->update([
            'status' => 'submitted',
            'submitted_by' => $homePlayers[0]->user_id,
        ]);
        $this->createSubmittedResultReport($match, $homePlayers[0], 'home', 12, 8);

        foreach ($homePlayers as $homePlayer) {
            $this->actingAs($homePlayer->user)
                ->getJson('/api/v1/me/matches/pending-actions')
                ->assertOk()
                ->assertJsonCount(0, 'data');
        }

        foreach ($awayPlayers as $awayPlayer) {
            $this->actingAs($awayPlayer->user)
                ->getJson('/api/v1/me/matches/pending-actions')
                ->assertOk()
                ->assertJsonCount(1, 'data')
                ->assertJsonPath('data.0.type', 'confirm_result')
                ->assertJsonPath('data.0.match.id', $match->id);
        }
    }
}
