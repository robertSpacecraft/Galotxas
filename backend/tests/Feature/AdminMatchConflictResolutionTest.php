<?php

namespace Tests\Feature;

use App\Models\MatchResultReport;
use App\Models\User;
use App\Services\Ranking\BuildCategoryRankingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
}
