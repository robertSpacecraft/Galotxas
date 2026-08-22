<?php

namespace Tests\Feature;

use App\Models\GameMatch;
use App\Models\Player;
use App\Models\User;
use App\Models\Venue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesMatchResultWorkflow;
use Tests\TestCase;

class CupResultWorkflowTest extends TestCase
{
    use CreatesMatchResultWorkflow;
    use RefreshDatabase;

    public function test_admin_can_validate_a_cup_result_and_the_category_view_shows_it(): void
    {
        [$match] = $this->createCupResultMatch();
        $admin = User::factory()->admin()->create();
        $venue = Venue::factory()->create();

        $this->actingAs($admin)
            ->patch(route('admin.categories.matches.update', [$match->round->category, $match]), [
                'scheduled_date' => '2026-09-12',
                'scheduled_time' => '18:30',
                'venue_id' => $venue->id,
                'status' => 'validated',
                'home_score' => 10,
                'away_score' => 7,
            ])
            ->assertRedirect()
            ->assertSessionHas('success', 'Partido actualizado correctamente.');

        $this->assertDatabaseHas('game_matches', [
            'id' => $match->id,
            'status' => 'validated',
            'home_score' => 10,
            'away_score' => 7,
            'winner_entry_id' => $match->home_entry_id,
            'submitted_by' => $admin->id,
            'validated_by' => $admin->id,
        ]);

        $this->actingAs($admin)
            ->get(route('admin.categories.show', $match->round->category))
            ->assertOk()
            ->assertSee('Semifinales')
            ->assertSee('value="10"', false)
            ->assertSee('value="7"', false)
            ->assertSee('value="validated" selected', false);
    }

    public function test_matching_participant_reports_validate_a_cup_result_visible_in_admin(): void
    {
        [$match, $homePlayer, $awayPlayer] = $this->createCupResultMatch();

        $this->actingAs($homePlayer->user)
            ->postJson("/api/v1/matches/{$match->id}/submit-result", [
                'home_score' => 10,
                'away_score' => 8,
            ])
            ->assertOk()
            ->assertJsonPath('data.match.status', 'submitted');

        $this->actingAs($awayPlayer->user)
            ->postJson("/api/v1/matches/{$match->id}/confirm-result")
            ->assertOk()
            ->assertJsonPath('data.match.status', 'validated');

        $this->assertDatabaseHas('game_matches', [
            'id' => $match->id,
            'status' => 'validated',
            'home_score' => 10,
            'away_score' => 8,
            'winner_entry_id' => $match->home_entry_id,
            'submitted_by' => $homePlayer->user_id,
            'validated_by' => $awayPlayer->user_id,
        ]);

        $this->assertCupResultVisibleInAdmin($match, 10, 8);
    }

    public function test_conflicting_cup_reports_can_be_resolved_and_seen_in_admin(): void
    {
        [$match, $homePlayer, $awayPlayer] = $this->createCupResultMatch();

        $this->actingAs($homePlayer->user)
            ->postJson("/api/v1/matches/{$match->id}/submit-result", [
                'home_score' => 10,
                'away_score' => 7,
            ])
            ->assertOk();
        $this->actingAs($awayPlayer->user)
            ->postJson("/api/v1/matches/{$match->id}/submit-result", [
                'home_score' => 6,
                'away_score' => 10,
            ])
            ->assertOk()
            ->assertJsonPath('data.match.status', 'under_review');

        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->post(route('admin.match-conflicts.resolve', $match), [
                'home_score' => 10,
                'away_score' => 9,
            ])
            ->assertRedirect(route('admin.match-conflicts.index'))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('game_matches', [
            'id' => $match->id,
            'status' => 'validated',
            'home_score' => 10,
            'away_score' => 9,
            'winner_entry_id' => $match->home_entry_id,
            'validated_by' => $admin->id,
        ]);

        $this->assertCupResultVisibleInAdmin($match, 10, 9);
    }

    public function test_admin_cannot_send_scores_with_a_status_that_does_not_admit_results(): void
    {
        [$match] = $this->createCupResultMatch();
        $admin = User::factory()->admin()->create();
        $venue = Venue::factory()->create();

        $this->actingAs($admin)
            ->from(route('admin.categories.show', $match->round->category))
            ->patch(route('admin.categories.matches.update', [$match->round->category, $match]), [
                'scheduled_date' => '2026-09-12',
                'scheduled_time' => '18:30',
                'venue_id' => $venue->id,
                'status' => 'scheduled',
                'home_score' => 10,
                'away_score' => 7,
            ])
            ->assertRedirect(route('admin.categories.show', $match->round->category))
            ->assertSessionHasErrors('status');

        $this->assertDatabaseHas('game_matches', [
            'id' => $match->id,
            'status' => 'scheduled',
            'home_score' => null,
            'away_score' => null,
            'winner_entry_id' => null,
        ]);
    }

    /**
     * @return array{GameMatch, Player, Player}
     */
    private function createCupResultMatch(): array
    {
        [$match, $homePlayer, $awayPlayer] = $this->createSinglesResultMatch();
        $match->round->update([
            'name' => 'Semifinales',
            'order' => 100,
            'type' => 'cup',
            'phase' => 'cup',
            'stage' => 'semifinal',
        ]);

        return [$match->fresh('round.category'), $homePlayer, $awayPlayer];
    }

    private function assertCupResultVisibleInAdmin(GameMatch $match, int $homeScore, int $awayScore): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->get(route('admin.categories.show', $match->round->category))
            ->assertOk()
            ->assertSee('Semifinales')
            ->assertSee('value="'.$homeScore.'"', false)
            ->assertSee('value="'.$awayScore.'"', false)
            ->assertSee('value="validated" selected', false);
    }
}
