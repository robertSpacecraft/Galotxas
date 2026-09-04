<?php

namespace Tests\Concerns;

use App\Models\Category;
use App\Models\CategoryEntry;
use App\Models\Championship;
use App\Models\GameMatch;
use App\Models\Player;
use App\Models\Round;
use App\Models\Team;
use App\Models\User;
use Illuminate\Support\Collection;

trait CreatesOfficialLeagueFixture
{
    /**
     * @return array{
     *     category: Category,
     *     entries: Collection<int, CategoryEntry>,
     *     players: Collection<int, Player>,
     *     rounds: Collection<int, Round>,
     *     matches: Collection<int, GameMatch>
     * }
     */
    protected function createReadySinglesLeague(
        int $entryCount = 3,
        bool $legacyRounds = false,
    ): array {
        $championship = Championship::factory()->create(['type' => 'singles']);
        $category = Category::factory()->create(['championship_id' => $championship->id]);
        $players = collect();
        $entries = collect();

        for ($index = 0; $index < $entryCount; $index++) {
            $user = User::factory()->create([
                'name' => 'Jugador '.($index + 1),
                'lastname' => 'Apellido '.($index + 1),
            ]);
            $player = Player::factory()->create([
                'user_id' => $user->id,
                'nickname' => 'Alias '.($index + 1),
                'birth_date' => '1990-01-01',
                'active' => true,
            ]);
            $players->push($player);
            $entries->push(CategoryEntry::factory()->playerEntry()->create([
                'category_id' => $category->id,
                'player_id' => $player->id,
                'status' => 'approved',
            ]));
        }

        [$rounds, $matches] = $this->createRoundRobin(
            $category,
            $entries,
            10,
            $legacyRounds,
        );

        return compact('category', 'entries', 'players', 'rounds', 'matches');
    }

    /**
     * @return array{
     *     category: Category,
     *     entries: Collection<int, CategoryEntry>,
     *     teams: Collection<int, Team>,
     *     players: Collection<int, Player>,
     *     rounds: Collection<int, Round>,
     *     matches: Collection<int, GameMatch>
     * }
     */
    protected function createReadyDoublesLeague(int $entryCount = 3): array
    {
        $championship = Championship::factory()->create(['type' => 'doubles']);
        $category = Category::factory()->create(['championship_id' => $championship->id]);
        $entries = collect();
        $teams = collect();
        $players = collect();

        for ($index = 0; $index < $entryCount; $index++) {
            $team = Team::factory()->create([
                'category_id' => $category->id,
                'name' => 'Equipo '.($index + 1),
            ]);
            $front = Player::factory()->create([
                'nickname' => 'Delante '.($index + 1),
                'birth_date' => '1990-01-01',
                'active' => true,
            ]);
            $back = Player::factory()->create([
                'nickname' => 'Zaguero '.($index + 1),
                'birth_date' => '1990-01-01',
                'active' => true,
            ]);
            $team->players()->attach($front->id, ['role_in_team' => 'front']);
            $team->players()->attach($back->id, ['role_in_team' => 'back']);

            $teams->push($team);
            $players->push($front, $back);
            $entries->push(CategoryEntry::factory()->teamEntry()->create([
                'category_id' => $category->id,
                'team_id' => $team->id,
                'status' => 'approved',
            ]));
        }

        [$rounds, $matches] = $this->createRoundRobin($category, $entries, 12);

        return compact('category', 'entries', 'teams', 'players', 'rounds', 'matches');
    }

    protected function createActiveAdmin(array $attributes = []): User
    {
        return User::factory()->admin()->create(array_merge([
            'name' => '  Ada  ',
            'lastname' => '  Administradora  ',
            'active' => true,
        ], $attributes));
    }

    /**
     * @param  Collection<int, CategoryEntry>  $entries
     * @return array{Collection<int, Round>, Collection<int, GameMatch>}
     */
    private function createRoundRobin(
        Category $category,
        Collection $entries,
        int $targetScore,
        bool $legacyRounds = false,
    ): array {
        $slots = $entries->values()->all();
        if (count($slots) % 2 !== 0) {
            $slots[] = null;
        }

        $roundCount = count($slots) - 1;
        $half = intdiv(count($slots), 2);
        $rounds = collect();
        $matches = collect();
        $lossScore = 2;

        for ($roundIndex = 0; $roundIndex < $roundCount; $roundIndex++) {
            $round = Round::factory()->create([
                'category_id' => $category->id,
                'name' => 'Jornada '.($roundIndex + 1),
                'order' => $roundIndex + 1,
                'type' => 'league',
                'phase' => $legacyRounds ? null : 'league',
                'stage' => $legacyRounds ? null : 'matchday',
            ]);
            $rounds->push($round);

            for ($pairIndex = 0; $pairIndex < $half; $pairIndex++) {
                $home = $slots[$pairIndex];
                $away = $slots[count($slots) - 1 - $pairIndex];

                if ($home === null || $away === null) {
                    continue;
                }

                $homeWins = $home->id < $away->id;
                $homeScore = $homeWins ? $targetScore : $lossScore;
                $awayScore = $homeWins ? $lossScore : $targetScore;
                $matches->push(GameMatch::factory()->create([
                    'round_id' => $round->id,
                    'home_entry_id' => $home->id,
                    'away_entry_id' => $away->id,
                    'status' => 'validated',
                    'home_score' => $homeScore,
                    'away_score' => $awayScore,
                    'winner_entry_id' => $homeWins ? $home->id : $away->id,
                ]));
                $lossScore++;
            }

            $fixed = array_shift($slots);
            $last = array_pop($slots);
            array_unshift($slots, $fixed);
            array_splice($slots, 1, 0, [$last]);
        }

        return [$rounds, $matches];
    }
}
