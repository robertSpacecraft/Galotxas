<?php

namespace App\Services\Ranking;

use InvalidArgumentException;

class ResolveMatchBasePointsService
{
    /**
     * @return array{home_points: int, away_points: int}
     */
    public function resolve(int $homeScore, int $awayScore): array
    {
        if ($homeScore === $awayScore) {
            throw new InvalidArgumentException('No se pueden repartir puntos para un partido empatado.');
        }

        $homeWon = $homeScore > $awayScore;
        $loserScore = $homeWon ? $awayScore : $homeScore;
        $winnerPoints = $loserScore >= 8 ? 2 : 3;
        $loserPoints = $loserScore >= 8 ? 1 : 0;

        return $homeWon
            ? ['home_points' => $winnerPoints, 'away_points' => $loserPoints]
            : ['home_points' => $loserPoints, 'away_points' => $winnerPoints];
    }
}
