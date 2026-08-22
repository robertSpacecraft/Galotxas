<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\CategoryEntry;
use App\Models\Championship;
use App\Models\GameMatch;
use App\Models\Player;
use App\Models\Round;
use App\Models\Season;
use App\Models\Venue;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublicCupScheduleApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_schedule_publishes_ordered_cup_stages_and_only_official_winners(): void
    {
        $season = Season::factory()->publiclyVisible()->create();
        $championship = Championship::factory()->publiclyVisible()->create([
            'season_id' => $season->id,
            'type' => 'singles',
        ]);
        $category = Category::factory()->publiclyVisible()->create([
            'championship_id' => $championship->id,
        ]);
        $homePlayer = Player::factory()->create([
            'birth_date' => '1990-01-01',
            'nickname' => 'Pilotari Blau',
        ]);
        $awayPlayer = Player::factory()->create([
            'birth_date' => '1991-01-01',
            'nickname' => 'Pilotari Roig',
        ]);
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
        $venue = Venue::factory()->create(['name' => 'Trinquet de la Copa']);

        $finalRound = $this->cupRound($category, 'Final', 200, 'final');
        $semifinalRound = $this->cupRound($category, 'Semifinales', 100, 'semifinal');
        $thirdPlaceRound = $this->cupRound($category, '3º y 4º', 201, 'third_place');

        $this->cupMatch($semifinalRound, $homeEntry, $awayEntry, $venue, [
            'status' => 'scheduled',
            'home_score' => 10,
            'away_score' => 8,
            'winner_entry_id' => $homeEntry->id,
        ]);
        $final = $this->cupMatch($finalRound, $homeEntry, $awayEntry, $venue, [
            'status' => 'validated',
            'home_score' => 10,
            'away_score' => 7,
            'winner_entry_id' => $homeEntry->id,
        ]);
        $this->cupMatch($thirdPlaceRound, $homeEntry, $awayEntry, $venue, [
            'status' => 'validated',
            'home_score' => 6,
            'away_score' => 10,
            'winner_entry_id' => $awayEntry->id,
        ]);

        Model::preventLazyLoading();

        try {
            $response = $this->getJson('/api/v1/categories/'.$category->id.'/schedule');
        } finally {
            Model::preventLazyLoading(false);
        }

        $response
            ->assertOk()
            ->assertJsonPath('data.0.phase', 'cup')
            ->assertJsonPath('data.0.stage', 'semifinal')
            ->assertJsonPath('data.0.matches.0.home_score', null)
            ->assertJsonPath('data.0.matches.0.away_score', null)
            ->assertJsonPath('data.0.matches.0.winner_entry', null)
            ->assertJsonPath('data.1.phase', 'cup')
            ->assertJsonPath('data.1.stage', 'final')
            ->assertJsonPath('data.1.matches.0.id', $final->id)
            ->assertJsonPath('data.1.matches.0.home_score', 10)
            ->assertJsonPath('data.1.matches.0.away_score', 7)
            ->assertJsonPath('data.1.matches.0.winner_entry.entry_type', 'player')
            ->assertJsonPath('data.1.matches.0.winner_entry.public_display_name', 'Pilotari Blau')
            ->assertJsonPath('data.2.stage', 'third_place')
            ->assertJsonPath('data.2.matches.0.winner_entry.public_display_name', 'Pilotari Roig')
            ->assertJsonMissingPath('data.1.matches.0.winner_entry.id')
            ->assertJsonMissingPath('data.1.matches.0.winner_entry.player')
            ->assertJsonMissingPath('data.1.matches.0.winner_entry_id')
            ->assertJsonMissingPath('data.1.matches.0.submitted_by')
            ->assertJsonMissingPath('data.1.matches.0.validated_by')
            ->assertJsonMissingPath('data.1.matches.0.result_reports');
    }

    private function cupRound(Category $category, string $name, int $order, string $stage): Round
    {
        return Round::factory()->create([
            'category_id' => $category->id,
            'name' => $name,
            'order' => $order,
            'type' => 'cup',
            'phase' => 'cup',
            'stage' => $stage,
        ]);
    }

    private function cupMatch(
        Round $round,
        CategoryEntry $homeEntry,
        CategoryEntry $awayEntry,
        Venue $venue,
        array $overrides
    ): GameMatch {
        return GameMatch::factory()->create([
            'round_id' => $round->id,
            'venue_id' => $venue->id,
            'home_entry_id' => $homeEntry->id,
            'away_entry_id' => $awayEntry->id,
            'scheduled_date' => '2026-09-12 18:30:00',
            ...$overrides,
        ]);
    }
}
