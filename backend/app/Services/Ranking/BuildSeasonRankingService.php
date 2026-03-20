<?php

namespace App\Services\Ranking;

use App\Models\Player;
use App\Models\Season;
use App\Models\GameMatch;
use Illuminate\Support\Collection;

class BuildSeasonRankingService
{
    public function __construct(
        private readonly ResolveEntryPlayerContributionsService $contributionsService
    ) {
    }

    public function build(Season $season): Collection
    {
        $matches = GameMatch::query()
            ->whereHas('round.category.championship', function ($query) use ($season) {
                $query->where('season_id', $season->id);
            })
            ->where('status', 'validated')
            ->whereNotNull('home_score')
            ->whereNotNull('away_score')
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

            $homeRawPoints = $homeWon ? 3 : ($homeScore >= 8 ? 1 : 0);
            $awayRawPoints = $homeWon ? ($awayScore >= 8 ? 1 : 0) : 3;

            foreach ($homeContributions as $contribution) {
                /** @var \App\Models\Player $player */
                $player = $contribution['player'];
                $weight = (float) $contribution['weight'];

                $row = $this->getOrCreateRow($table, $player);

                $row['played']++;
                $row['wins'] += $homeWon ? 1 : 0;
                $row['losses'] += $homeWon ? 0 : 1;
                $row['raw_points'] += $homeRawPoints * $weight;
                $row['weighted_points'] += ($homeRawPoints * $levelMultiplier) * $weight;
                $row['games_for'] += $homeScore * $weight;
                $row['games_against'] += $awayScore * $weight;
                $row['championships_played'][$championship->id] = $championship->name;
                $row['categories_played'][$category->id] = $category->name;

                $table->put($player->id, $row);
            }

            foreach ($awayContributions as $contribution) {
                /** @var \App\Models\Player $player */
                $player = $contribution['player'];
                $weight = (float) $contribution['weight'];

                $row = $this->getOrCreateRow($table, $player);

                $row['played']++;
                $row['wins'] += $homeWon ? 0 : 1;
                $row['losses'] += $homeWon ? 1 : 0;
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
            $row['championships_played_count'] = count($row['championships_played']);
            $row['categories_played_count'] = count($row['categories_played']);
            $row['championships_played_list'] = array_values($row['championships_played']);
            $row['categories_played_list'] = array_values($row['categories_played']);

            unset($row['championships_played'], $row['categories_played']);

            return $row;
        });

        $sorted = $table->sort(function (array $a, array $b) {
            if ($a['weighted_points'] !== $b['weighted_points']) {
                return $b['weighted_points'] <=> $a['weighted_points'];
            }

            if ($a['wins'] !== $b['wins']) {
                return $b['wins'] <=> $a['wins'];
            }

            if ($a['games_diff'] !== $b['games_diff']) {
                return $b['games_diff'] <=> $a['games_diff'];
            }

            if ($a['games_for'] !== $b['games_for']) {
                return $b['games_for'] <=> $a['games_for'];
            }

            return strcmp($a['name'], $b['name']);
        })->values();

        return $sorted->map(function (array $row, int $index) {
            $row['position'] = $index + 1;
            return $row;
        });
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
            'raw_points' => 0.0,
            'weighted_points' => 0.0,
            'games_for' => 0.0,
            'games_against' => 0.0,
            'games_diff' => 0.0,
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
