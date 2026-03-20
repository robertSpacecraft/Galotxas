<?php

namespace App\Services\Ranking;

use App\Enums\ChampionshipType;
use App\Models\GameMatch;
use App\Models\Player;
use Illuminate\Support\Collection;

class BuildAllTimeRankingService
{
    private const MIN_MATCHES_FOR_OFFICIAL_RANKING = 10;

    public function __construct(
        private readonly ResolveEntryPlayerContributionsService $contributionsService
    ) {
    }

    public function build(): Collection
    {
        $matches = GameMatch::query()
            ->where('status', 'validated')
            ->whereNotNull('home_score')
            ->whereNotNull('away_score')
            ->whereHas('round.category.championship')
            ->with([
                'round.category.championship',
                'homeEntry.player.user',
                'homeEntry.team.players.user',
                'awayEntry.player.user',
                'awayEntry.team.players.user',
            ])
            ->get();

        $table = collect();

        foreach ($matches as $match) {
            $category = $match->round?->category;
            $championship = $category?->championship;

            if (!$category || !$championship) {
                continue;
            }

            $levelMultiplier = $this->levelMultiplier((int) $category->level);

            $homeScore = (int) $match->home_score;
            $awayScore = (int) $match->away_score;

            $homeContributions = $this->contributionsService->resolve($match->homeEntry);
            $awayContributions = $this->contributionsService->resolve($match->awayEntry);

            if ($homeContributions->isEmpty() || $awayContributions->isEmpty()) {
                continue;
            }

            $homeWon = $homeScore > $awayScore;
            $isDoubles = $championship->type === ChampionshipType::DOUBLES;

            $homeRawPoints = $homeWon ? 3 : ($homeScore >= 8 ? 1 : 0);
            $awayRawPoints = $homeWon ? ($awayScore >= 8 ? 1 : 0) : 3;

            foreach ($homeContributions as $contribution) {
                /** @var Player $player */
                $player = $contribution['player'];
                $weight = (float) $contribution['weight'];

                $row = $this->getOrCreateRow($table, $player);

                $row['played']++;
                $row['wins'] += $homeWon ? 1 : 0;
                $row['losses'] += $homeWon ? 0 : 1;

                if ($isDoubles) {
                    $row['played_doubles']++;
                    $row['wins_doubles'] += $homeWon ? 1 : 0;
                    $row['losses_doubles'] += $homeWon ? 0 : 1;
                } else {
                    $row['played_singles']++;
                    $row['wins_singles'] += $homeWon ? 1 : 0;
                    $row['losses_singles'] += $homeWon ? 0 : 1;
                }

                $row['raw_points'] += $homeRawPoints * $weight;
                $row['weighted_points'] += ($homeRawPoints * $levelMultiplier) * $weight;
                $row['games_for'] += $homeScore * $weight;
                $row['games_against'] += $awayScore * $weight;
                $row['championships_played'][$championship->id] = $championship->name;
                $row['categories_played'][$category->id] = $category->name;

                $table->put($player->id, $row);
            }

            foreach ($awayContributions as $contribution) {
                /** @var Player $player */
                $player = $contribution['player'];
                $weight = (float) $contribution['weight'];

                $row = $this->getOrCreateRow($table, $player);

                $row['played']++;
                $row['wins'] += $homeWon ? 0 : 1;
                $row['losses'] += $homeWon ? 1 : 0;

                if ($isDoubles) {
                    $row['played_doubles']++;
                    $row['wins_doubles'] += $homeWon ? 0 : 1;
                    $row['losses_doubles'] += $homeWon ? 1 : 0;
                } else {
                    $row['played_singles']++;
                    $row['wins_singles'] += $homeWon ? 0 : 1;
                    $row['losses_singles'] += $homeWon ? 1 : 0;
                }

                $row['raw_points'] += $awayRawPoints * $weight;
                $row['weighted_points'] += ($awayRawPoints * $levelMultiplier) * $weight;
                $row['games_for'] += $awayScore * $weight;
                $row['games_against'] += $homeScore * $weight;
                $row['championships_played'][$championship->id] = $championship->name;
                $row['categories_played'][$category->id] = $category->name;

                $table->put($player->id, $row);
            }
        }

        $table = $table->map(function (array $row) {
            $row['games_diff'] = $row['games_for'] - $row['games_against'];
            $row['win_rate'] = $row['played'] > 0 ? ($row['wins'] / $row['played']) * 100 : 0.0;
            $row['weighted_points_per_match'] = $row['played'] > 0 ? $row['weighted_points'] / $row['played'] : 0.0;
            $row['games_diff_per_match'] = $row['played'] > 0 ? $row['games_diff'] / $row['played'] : 0.0;
            $row['official_ranking'] = $row['played'] >= self::MIN_MATCHES_FOR_OFFICIAL_RANKING;
            $row['matches_needed_for_official_ranking'] = max(0, self::MIN_MATCHES_FOR_OFFICIAL_RANKING - $row['played']);
            $row['championships_played_list'] = array_values($row['championships_played']);
            $row['categories_played_list'] = array_values($row['categories_played']);
            unset($row['championships_played'], $row['categories_played']);

            return $row;
        });

        $official = $table
            ->filter(fn (array $row) => $row['official_ranking'])
            ->sort(function (array $a, array $b) {
                if ($a['weighted_points_per_match'] !== $b['weighted_points_per_match']) {
                    return $b['weighted_points_per_match'] <=> $a['weighted_points_per_match'];
                }

                if ($a['win_rate'] !== $b['win_rate']) {
                    return $b['win_rate'] <=> $a['win_rate'];
                }

                if ($a['games_diff_per_match'] !== $b['games_diff_per_match']) {
                    return $b['games_diff_per_match'] <=> $a['games_diff_per_match'];
                }

                if ($a['weighted_points'] !== $b['weighted_points']) {
                    return $b['weighted_points'] <=> $a['weighted_points'];
                }

                return strcmp($a['name'], $b['name']);
            })
            ->values()
            ->map(function (array $row, int $index) {
                $row['position'] = $index + 1;
                return $row;
            });

        $nonOfficial = $table
            ->filter(fn (array $row) => !$row['official_ranking'])
            ->sort(function (array $a, array $b) {
                if ($a['played'] !== $b['played']) {
                    return $b['played'] <=> $a['played'];
                }

                if ($a['weighted_points_per_match'] !== $b['weighted_points_per_match']) {
                    return $b['weighted_points_per_match'] <=> $a['weighted_points_per_match'];
                }

                return strcmp($a['name'], $b['name']);
            })
            ->values()
            ->map(function (array $row) {
                $row['position'] = null;
                return $row;
            });

        return $official->concat($nonOfficial)->values();
    }

    private function getOrCreateRow(Collection $table, Player $player): array
    {
        return $table->get($player->id, [
            'player_id' => $player->id,
            'player' => $player,
            'name' => $player->nickname ?: trim(($player->user->name ?? '') . ' ' . ($player->user->lastname ?? '')),
            'played' => 0,
            'wins' => 0,
            'losses' => 0,
            'played_singles' => 0,
            'wins_singles' => 0,
            'losses_singles' => 0,
            'played_doubles' => 0,
            'wins_doubles' => 0,
            'losses_doubles' => 0,
            'raw_points' => 0.0,
            'weighted_points' => 0.0,
            'games_for' => 0.0,
            'games_against' => 0.0,
            'games_diff' => 0.0,
            'win_rate' => 0.0,
            'weighted_points_per_match' => 0.0,
            'games_diff_per_match' => 0.0,
            'official_ranking' => false,
            'matches_needed_for_official_ranking' => self::MIN_MATCHES_FOR_OFFICIAL_RANKING,
            'championships_played' => [],
            'categories_played' => [],
        ]);
    }

    private function levelMultiplier(int $level): float
    {
        return match ($level) {
            1 => 1.60,
            2 => 1.35,
            3 => 1.15,
            default => 1.00,
        };
    }
}
