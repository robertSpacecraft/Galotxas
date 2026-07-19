<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\CategoryEntry;
use App\Models\Championship;
use App\Models\GameMatch;
use App\Models\MatchResultReport;
use App\Models\Player;
use App\Models\Round;
use App\Models\Season;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublicMatchResourceTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_match_is_available_without_authentication_and_hides_internal_data(): void
    {
        $match = $this->createMatchWithInternalTrace('validated');

        $response = $this->getJson("/api/v1/matches/{$match->id}");

        $response
            ->assertOk()
            ->assertJsonPath('data.id', $match->id)
            ->assertJsonPath('data.status', 'validated')
            ->assertJsonPath('data.home_score', 10)
            ->assertJsonPath('data.away_score', 7)
            ->assertJsonPath('data.winner_entry_id', $match->home_entry_id)
            ->assertJsonMissingPath('data.submitted_by')
            ->assertJsonMissingPath('data.validated_by')
            ->assertJsonMissingPath('data.submitted_by_user')
            ->assertJsonMissingPath('data.validated_by_user')
            ->assertJsonMissingPath('data.result_reports')
            ->assertJsonMissingPath('data.created_at')
            ->assertJsonMissingPath('data.updated_at');

        $content = $response->getContent();
        $this->assertStringNotContainsString('reporter@example.com', $content);
        $this->assertStringNotContainsString('Comentario interno sensible', $content);
        $this->assertStringNotContainsString('validator@example.com', $content);
    }

    public function test_public_match_hides_unvalidated_scores(): void
    {
        $match = $this->createMatchWithInternalTrace('submitted');

        $this->getJson("/api/v1/matches/{$match->id}")
            ->assertOk()
            ->assertJsonPath('data.status', 'submitted')
            ->assertJsonPath('data.home_score', null)
            ->assertJsonPath('data.away_score', null)
            ->assertJsonPath('data.winner_entry_id', null)
            ->assertJsonPath('data.winner_entry', null);
    }

    public function test_admin_conflict_endpoint_keeps_internal_traceability(): void
    {
        $match = $this->createMatchWithInternalTrace('under_review');
        $admin = User::factory()->admin()->create();
        $token = $admin->createToken('admin-token')->plainTextToken;

        $this->withToken($token)
            ->getJson("/api/v1/admin/matches/{$match->id}/conflict")
            ->assertOk()
            ->assertJsonPath('data.match.submitted_by', $match->submitted_by)
            ->assertJsonPath('data.match.validated_by', $match->validated_by)
            ->assertJsonPath('data.reports.0.comment', 'Comentario interno sensible')
            ->assertJsonPath('data.reports.0.user.email', 'reporter@example.com');
    }

    private function createMatchWithInternalTrace(string $status): GameMatch
    {
        $season = Season::factory()->publiclyVisible()->create();
        $championship = Championship::factory()->publiclyVisible()->create([
            'season_id' => $season->id,
        ]);
        $category = Category::factory()->publiclyVisible()->create([
            'championship_id' => $championship->id,
        ]);
        $round = Round::factory()->for($category)->create();
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
        $reporter = User::factory()->create(['email' => 'reporter@example.com']);
        $validator = User::factory()->create(['email' => 'validator@example.com']);

        $match = GameMatch::factory()->create([
            'round_id' => $round->id,
            'home_entry_id' => $homeEntry->id,
            'away_entry_id' => $awayEntry->id,
            'winner_entry_id' => $homeEntry->id,
            'status' => $status,
            'home_score' => 10,
            'away_score' => 7,
            'submitted_by' => $reporter->id,
            'validated_by' => $validator->id,
        ]);

        MatchResultReport::query()->create([
            'game_match_id' => $match->id,
            'user_id' => $reporter->id,
            'player_id' => $homePlayer->id,
            'side' => 'home',
            'home_score' => 10,
            'away_score' => 7,
            'status' => 'submitted',
            'comment' => 'Comentario interno sensible',
        ]);

        return $match;
    }
}
