<?php

namespace App\Services;

use App\Enums\ChampionshipType;
use App\Models\GameMatch;
use InvalidArgumentException;

class MatchResultService
{
    public function getTargetScore(GameMatch $match): int
    {
        $match->loadMissing('round.category.championship');

        return $match->round->category->championship->type === ChampionshipType::DOUBLES
            ? 12
            : 10;
    }

    public function validateScores(GameMatch $match, ?int $homeScore, ?int $awayScore, string $status): void
    {
        $statusesWithScores = ['submitted', 'validated'];

        if (in_array($status, ['scheduled', 'postponed', 'cancelled'], true)) {
            return;
        }

        if (!in_array($status, $statusesWithScores, true)) {
            throw new InvalidArgumentException('El estado del partido no es válido.');
        }

        if ($homeScore === null || $awayScore === null) {
            throw new InvalidArgumentException('Debes indicar ambos tanteos para guardar un resultado.');
        }

        if ($homeScore < 0 || $awayScore < 0) {
            throw new InvalidArgumentException('Los tanteos no pueden ser negativos.');
        }

        if ($homeScore === $awayScore) {
            throw new InvalidArgumentException('No puede haber empate en una partida.');
        }

        $target = $this->getTargetScore($match);

        $oneReachedTarget = $homeScore === $target || $awayScore === $target;

        if (!$oneReachedTarget) {
            throw new InvalidArgumentException("Uno de los dos tanteos debe alcanzar exactamente {$target} juegos.");
        }

        if ($homeScore > $target || $awayScore > $target) {
            throw new InvalidArgumentException("Ningún tanteo puede superar {$target} juegos.");
        }

        if ($homeScore === $target && $awayScore >= $target) {
            throw new InvalidArgumentException('El rival debe quedar por debajo del tanteo máximo.');
        }

        if ($awayScore === $target && $homeScore >= $target) {
            throw new InvalidArgumentException('El rival debe quedar por debajo del tanteo máximo.');
        }
    }

    public function resolveWinnerSide(GameMatch $match, ?int $homeScore, ?int $awayScore): ?string
    {
        if ($homeScore === null || $awayScore === null) {
            return null;
        }

        return $homeScore > $awayScore ? 'home' : 'away';
    }

    public function getCategoryPointsForLoser(int $loserScore): int
    {
        return $loserScore >= 8 ? 1 : 0;
    }
}
