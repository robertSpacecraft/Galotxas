<?php

namespace App\Services;

use App\Enums\ChampionshipType;
use InvalidArgumentException;

class MatchScoreRulesService
{
    public const SINGLES_TARGET_SCORE = 10;

    public const DOUBLES_TARGET_SCORE = 12;

    public function targetScore(ChampionshipType|string $type): int
    {
        $value = $type instanceof ChampionshipType ? $type->value : $type;

        return $value === ChampionshipType::DOUBLES->value
            ? self::DOUBLES_TARGET_SCORE
            : self::SINGLES_TARGET_SCORE;
    }

    public function validate(
        ChampionshipType|string $type,
        ?int $homeScore,
        ?int $awayScore,
    ): void {
        if ($homeScore === null || $awayScore === null) {
            throw new InvalidArgumentException('Debes indicar ambos tanteos para guardar un resultado.');
        }

        if ($homeScore < 0 || $awayScore < 0) {
            throw new InvalidArgumentException('Los tanteos no pueden ser negativos.');
        }

        if ($homeScore === $awayScore) {
            throw new InvalidArgumentException('No puede haber empate en Galotxas.');
        }

        $targetScore = $this->targetScore($type);

        if ($homeScore !== $targetScore && $awayScore !== $targetScore) {
            throw new InvalidArgumentException("Uno de los dos equipos/jugadores debe alcanzar {$targetScore} juegos.");
        }

        if ($homeScore > $targetScore || $awayScore > $targetScore) {
            throw new InvalidArgumentException("No se pueden superar los {$targetScore} juegos.");
        }
    }
}
