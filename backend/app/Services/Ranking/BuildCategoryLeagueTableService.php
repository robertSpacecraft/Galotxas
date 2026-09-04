<?php

namespace App\Services\Ranking;

use Illuminate\Support\Collection;

class BuildCategoryLeagueTableService
{
    public function __construct(
        private readonly ResolveMatchBasePointsService $basePointsService,
    ) {}

    /**
     * @param  Collection<int, mixed>  $entries
     * @param  Collection<int, mixed>  $matches
     * @param  array<int, string>  $technicalNames
     */
    public function build(
        Collection $entries,
        Collection $matches,
        array $technicalNames = [],
    ): CategoryLeagueTableResult {
        $table = collect();

        foreach ($entries as $entry) {
            $entryId = (int) $entry->id;
            $table->put($entryId, [
                'entry_id' => $entryId,
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
            $homeId = (int) $match->home_entry_id;
            $awayId = (int) $match->away_entry_id;

            if (! $table->has($homeId) || ! $table->has($awayId)) {
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

            $basePoints = $this->basePointsService->resolve($homeScore, $awayScore);
            $home['points'] += $basePoints['home_points'];
            $away['points'] += $basePoints['away_points'];

            if ($homeScore > $awayScore) {
                $home['wins']++;
                $away['losses']++;
            } else {
                $away['wins']++;
                $home['losses']++;
            }

            $table->put($homeId, $home);
            $table->put($awayId, $away);
        }

        $table = $table->map(function (array $row): array {
            $row['games_diff'] = $row['games_for'] - $row['games_against'];

            return $row;
        })->values();

        $headToHead = $this->headToHeadMatrix($matches);
        $unsupported = [];

        $rows = $table
            ->groupBy('points')
            ->sortKeysDesc()
            ->flatMap(function (Collection $group) use ($headToHead, $technicalNames, &$unsupported): Collection {
                $group = $group->values();

                if ($group->count() === 2) {
                    $first = $group->get(0);
                    $second = $group->get(1);
                    $comparison = $this->compareHeadToHead(
                        $first['entry_id'],
                        $second['entry_id'],
                        $headToHead,
                    );

                    if ($comparison !== 0) {
                        return $comparison < 0
                            ? collect([$first, $second])
                            : collect([$second, $first]);
                    }

                    $unsupported[] = [$first['entry_id'], $second['entry_id']];
                } elseif ($group->count() > 2) {
                    $group
                        ->groupBy(fn (array $row): string => $row['games_diff'].'|'.$row['games_for'])
                        ->filter(fn (Collection $same): bool => $same->count() > 1)
                        ->each(function (Collection $same) use (&$unsupported): void {
                            $unsupported[] = $same->pluck('entry_id')->map(fn ($id): int => (int) $id)->sort()->values()->all();
                        });
                }

                return $group
                    ->sort(fn (array $a, array $b): int => $this->compareGlobalCriteria($a, $b, $technicalNames))
                    ->values();
            })
            ->values()
            ->map(function (array $row, int $index): array {
                $row['position'] = $index + 1;

                return $row;
            });

        return new CategoryLeagueTableResult($rows, $unsupported);
    }

    /** @return array<int, array<int, array{wins: int, games_diff: int}>> */
    private function headToHeadMatrix(Collection $matches): array
    {
        $matrix = [];

        foreach ($matches as $match) {
            $homeId = (int) $match->home_entry_id;
            $awayId = (int) $match->away_entry_id;
            $homeScore = (int) $match->home_score;
            $awayScore = (int) $match->away_score;
            $matrix[$homeId][$awayId] ??= ['wins' => 0, 'games_diff' => 0];
            $matrix[$awayId][$homeId] ??= ['wins' => 0, 'games_diff' => 0];

            if ($homeScore > $awayScore) {
                $matrix[$homeId][$awayId]['wins']++;
            } else {
                $matrix[$awayId][$homeId]['wins']++;
            }

            $matrix[$homeId][$awayId]['games_diff'] += $homeScore - $awayScore;
            $matrix[$awayId][$homeId]['games_diff'] += $awayScore - $homeScore;
        }

        return $matrix;
    }

    private function compareHeadToHead(int $entryA, int $entryB, array $matrix): int
    {
        $aVsB = $matrix[$entryA][$entryB] ?? ['wins' => 0, 'games_diff' => 0];
        $bVsA = $matrix[$entryB][$entryA] ?? ['wins' => 0, 'games_diff' => 0];

        return $aVsB['wins'] !== $bVsA['wins']
            ? $bVsA['wins'] <=> $aVsB['wins']
            : $bVsA['games_diff'] <=> $aVsB['games_diff'];
    }

    /** @param array<int, string> $technicalNames */
    private function compareGlobalCriteria(array $a, array $b, array $technicalNames): int
    {
        if ($a['games_diff'] !== $b['games_diff']) {
            return $b['games_diff'] <=> $a['games_diff'];
        }

        if ($a['games_for'] !== $b['games_for']) {
            return $b['games_for'] <=> $a['games_for'];
        }

        $nameComparison = strcmp(
            $technicalNames[$a['entry_id']] ?? '',
            $technicalNames[$b['entry_id']] ?? '',
        );

        return $nameComparison !== 0
            ? $nameComparison
            : $a['entry_id'] <=> $b['entry_id'];
    }
}
