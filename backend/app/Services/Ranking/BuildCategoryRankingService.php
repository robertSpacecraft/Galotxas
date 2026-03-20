<?php

namespace App\Services\Ranking;

use App\Models\Category;
use App\Models\GameMatch;
use Illuminate\Support\Collection;

class BuildCategoryRankingService
{
    public function build(Category $category): Collection
    {
        $category->loadMissing([
            'championship',
            'entries.player.user',
            'entries.team.players.user',
        ]);

        $entries = $category->entries()
            ->where('status', 'approved')
            ->with([
                'player.user',
                'team.players.user',
            ])
            ->get()
            ->keyBy('id');

        $matches = GameMatch::query()
            ->whereHas('round', function ($query) use ($category) {
                $query->where('category_id', $category->id)
                    ->where('type', 'league');
            })
            ->where('status', 'validated')
            ->whereNotNull('home_score')
            ->whereNotNull('away_score')
            ->with([
                'homeEntry.player.user',
                'homeEntry.team.players.user',
                'awayEntry.player.user',
                'awayEntry.team.players.user',
            ])
            ->get();

        $table = collect();

        foreach ($entries as $entry) {
            $table->put($entry->id, [
                'entry_id' => $entry->id,
                'entry' => $entry,
                'name' => $this->resolveEntryName($entry),
                'played' => 0,
                'wins' => 0,
                'losses' => 0,
                'points' => 0,
                'games_for' => 0,
                'games_against' => 0,
                'games_diff' => 0,
            ]);
        }

        foreach ($matches as $match) {
            $homeId = $match->home_entry_id;
            $awayId = $match->away_entry_id;

            if (!$table->has($homeId) || !$table->has($awayId)) {
                continue;
            }

            $home = $table->get($homeId);
            $away = $table->get($awayId);

            $homeScore = (int) $match->home_score;
            $awayScore = (int) $match->away_score;

            $home['played']++;
            $away['played']++;

            $home['games_for'] += $homeScore;
            $home['games_against'] += $awayScore;

            $away['games_for'] += $awayScore;
            $away['games_against'] += $homeScore;

            if ($homeScore > $awayScore) {
                $home['wins']++;
                $away['losses']++;

                $home['points'] += 3;
                $away['points'] += $awayScore >= 8 ? 1 : 0;
            } else {
                $away['wins']++;
                $home['losses']++;

                $away['points'] += 3;
                $home['points'] += $homeScore >= 8 ? 1 : 0;
            }

            $table->put($homeId, $home);
            $table->put($awayId, $away);
        }

        $table = $table->map(function (array $row) {
            $row['games_diff'] = $row['games_for'] - $row['games_against'];
            return $row;
        })->values();

        $headToHeadMatrix = $this->buildHeadToHeadMatrix($matches);

        $sorted = $table->sort(function (array $a, array $b) use ($headToHeadMatrix) {
            // 1) puntos
            if ($a['points'] !== $b['points']) {
                return $b['points'] <=> $a['points'];
            }

            // 2) enfrentamiento directo solo si el empate es entre 2 participantes
            $headToHead = $this->compareHeadToHead($a['entry_id'], $b['entry_id'], $headToHeadMatrix);
            if ($headToHead !== 0) {
                return $headToHead;
            }

            // 3) diferencia de juegos
            if ($a['games_diff'] !== $b['games_diff']) {
                return $b['games_diff'] <=> $a['games_diff'];
            }

            // 4) juegos a favor
            if ($a['games_for'] !== $b['games_for']) {
                return $b['games_for'] <=> $a['games_for'];
            }

            // 5) orden estable
            return strcmp($a['name'], $b['name']);
        })->values();

        return $sorted->map(function (array $row, int $index) {
            $row['position'] = $index + 1;
            return $row;
        });
    }

    private function resolveEntryName($entry): string
    {
        if ($entry->entry_type === 'player' && $entry->player) {
            return $entry->player->nickname
                ?: trim($entry->player->user->name . ' ' . $entry->player->user->lastname);
        }

        if ($entry->entry_type === 'team' && $entry->team) {
            return $entry->team->name;
        }

        return 'Participante #' . $entry->id;
    }

    private function buildHeadToHeadMatrix(Collection $matches): array
    {
        $matrix = [];

        foreach ($matches as $match) {
            $homeId = $match->home_entry_id;
            $awayId = $match->away_entry_id;
            $homeScore = (int) $match->home_score;
            $awayScore = (int) $match->away_score;

            if (!isset($matrix[$homeId][$awayId])) {
                $matrix[$homeId][$awayId] = ['wins' => 0, 'games_diff' => 0];
            }

            if (!isset($matrix[$awayId][$homeId])) {
                $matrix[$awayId][$homeId] = ['wins' => 0, 'games_diff' => 0];
            }

            if ($homeScore > $awayScore) {
                $matrix[$homeId][$awayId]['wins']++;
            } else {
                $matrix[$awayId][$homeId]['wins']++;
            }

            $matrix[$homeId][$awayId]['games_diff'] += ($homeScore - $awayScore);
            $matrix[$awayId][$homeId]['games_diff'] += ($awayScore - $homeScore);
        }

        return $matrix;
    }

    private function compareHeadToHead(int $entryA, int $entryB, array $matrix): int
    {
        $aVsB = $matrix[$entryA][$entryB] ?? ['wins' => 0, 'games_diff' => 0];
        $bVsA = $matrix[$entryB][$entryA] ?? ['wins' => 0, 'games_diff' => 0];

        if ($aVsB['wins'] !== $bVsA['wins']) {
            return $bVsA['wins'] <=> $aVsB['wins'];
        }

        if ($aVsB['games_diff'] !== $bVsA['games_diff']) {
            return $bVsA['games_diff'] <=> $aVsB['games_diff'];
        }

        return 0;
    }
}
