<?php

namespace Tests\Concerns;

use App\Models\Category;
use App\Models\CategoryEntry;
use App\Models\Championship;
use App\Models\GameMatch;
use App\Models\MatchResultReport;
use App\Models\Player;
use App\Models\Round;
use App\Models\Season;
use App\Models\Team;
use App\Models\User;

trait CreatesMatchResultWorkflow
{
    /**
     * @return array{GameMatch, Player, Player}
     */
    protected function createSinglesResultMatch(array $matchOverrides = []): array
    {
        $season = Season::factory()->publiclyVisible()->create();
        $championship = Championship::factory()->publiclyVisible()->create([
            'season_id' => $season->id,
            'type' => 'singles',
        ]);
        $category = Category::factory()->publiclyVisible()->create([
            'championship_id' => $championship->id,
        ]);
        $round = $this->createResultRound($category);
        $homePlayer = $this->createResultPlayer();
        $awayPlayer = $this->createResultPlayer();
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
        $match = GameMatch::factory()->create(array_merge([
            'round_id' => $round->id,
            'home_entry_id' => $homeEntry->id,
            'away_entry_id' => $awayEntry->id,
            'status' => 'scheduled',
            'home_score' => null,
            'away_score' => null,
            'winner_entry_id' => null,
            'submitted_by' => null,
            'validated_by' => null,
        ], $matchOverrides));

        return [$match, $homePlayer, $awayPlayer];
    }

    /**
     * @return array{GameMatch, array<int, Player>, array<int, Player>}
     */
    protected function createDoublesResultMatch(array $matchOverrides = []): array
    {
        $season = Season::factory()->publiclyVisible()->create();
        $championship = Championship::factory()->publiclyVisible()->create([
            'season_id' => $season->id,
            'type' => 'doubles',
        ]);
        $category = Category::factory()->publiclyVisible()->create([
            'championship_id' => $championship->id,
        ]);
        $round = $this->createResultRound($category);
        $homeTeam = Team::factory()->create(['category_id' => $category->id]);
        $awayTeam = Team::factory()->create(['category_id' => $category->id]);
        $homePlayers = [$this->createResultPlayer(), $this->createResultPlayer()];
        $awayPlayers = [$this->createResultPlayer(), $this->createResultPlayer()];

        $homeTeam->players()->attach($homePlayers[0]->id, ['role_in_team' => 'front']);
        $homeTeam->players()->attach($homePlayers[1]->id, ['role_in_team' => 'back']);
        $awayTeam->players()->attach($awayPlayers[0]->id, ['role_in_team' => 'front']);
        $awayTeam->players()->attach($awayPlayers[1]->id, ['role_in_team' => 'back']);

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
        $match = GameMatch::factory()->create(array_merge([
            'round_id' => $round->id,
            'home_entry_id' => $homeEntry->id,
            'away_entry_id' => $awayEntry->id,
            'status' => 'scheduled',
            'home_score' => null,
            'away_score' => null,
            'winner_entry_id' => null,
            'submitted_by' => null,
            'validated_by' => null,
        ], $matchOverrides));

        return [$match, $homePlayers, $awayPlayers];
    }

    protected function createResultPlayer(): Player
    {
        $user = User::factory()->create();

        return Player::factory()->create([
            'user_id' => $user->id,
            'active' => true,
        ])->load('user');
    }

    protected function createSubmittedResultReport(
        GameMatch $match,
        Player $player,
        string $side,
        int $homeScore,
        int $awayScore,
        ?string $comment = null
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

    private function createResultRound(Category $category): Round
    {
        return Round::factory()->create([
            'category_id' => $category->id,
            'type' => 'league',
            'phase' => 'league',
            'stage' => 'matchday',
        ]);
    }
}
