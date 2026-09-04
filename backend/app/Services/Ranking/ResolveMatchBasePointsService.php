<?php

namespace App\Services\Ranking;

use InvalidArgumentException;

class ResolveMatchBasePointsService
{
    public const POINTS_TOTAL = 3;

    public const CLOSE_LOSS_MINIMUM = 8;

    public const WINNER_POINTS_CLOSE = 2;

    public const LOSER_POINTS_CLOSE = 1;

    public const WINNER_POINTS_OTHERWISE = 3;

    public const LOSER_POINTS_OTHERWISE = 0;

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
        $winnerPoints = $loserScore >= self::CLOSE_LOSS_MINIMUM
            ? self::WINNER_POINTS_CLOSE
            : self::WINNER_POINTS_OTHERWISE;
        $loserPoints = $loserScore >= self::CLOSE_LOSS_MINIMUM
            ? self::LOSER_POINTS_CLOSE
            : self::LOSER_POINTS_OTHERWISE;

        return $homeWon
            ? ['home_points' => $winnerPoints, 'away_points' => $loserPoints]
            : ['home_points' => $loserPoints, 'away_points' => $winnerPoints];
    }

    /** @return array<string, int|list<string>> */
    public function canonicalRuleset(int $targetScore): array
    {
        return [
            'match_target_score' => $targetScore,
            'points_total' => self::POINTS_TOTAL,
            'close_loss_minimum' => self::CLOSE_LOSS_MINIMUM,
            'winner_points_close' => self::WINNER_POINTS_CLOSE,
            'loser_points_close' => self::LOSER_POINTS_CLOSE,
            'winner_points_otherwise' => self::WINNER_POINTS_OTHERWISE,
            'loser_points_otherwise' => self::LOSER_POINTS_OTHERWISE,
            'two_participant_tie' => ['head_to_head'],
            'multi_participant_tie' => ['games_diff', 'games_for'],
        ];
    }
}
