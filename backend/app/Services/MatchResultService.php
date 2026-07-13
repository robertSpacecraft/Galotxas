<?php

namespace App\Services;

use App\Enums\ChampionshipType;
use App\Enums\GameMatchStatus;
use App\Models\GameMatch;
use App\Models\User;
use Illuminate\Support\Facades\DB;
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
        $statusesWithScores = [
            GameMatchStatus::SUBMITTED->value,
            GameMatchStatus::VALIDATED->value,
            GameMatchStatus::UNDER_REVIEW->value,
        ];

        if (in_array($status, ['scheduled', 'postponed', 'cancelled'], true)) {
            return;
        }

        if (! in_array($status, $statusesWithScores, true)) {
            throw new InvalidArgumentException('El estado del partido no es válido.');
        }

        if ($homeScore === null || $awayScore === null) {
            throw new InvalidArgumentException('Debes indicar ambos tanteos para guardar un resultado.');
        }

        if ($homeScore < 0 || $awayScore < 0) {
            throw new InvalidArgumentException('Los tanteos no pueden ser negativos.');
        }

        $targetScore = $this->getTargetScore($match);

        if ($homeScore === $awayScore) {
            throw new InvalidArgumentException('No puede haber empate en Galotxas.');
        }

        if ($homeScore !== $targetScore && $awayScore !== $targetScore) {
            throw new InvalidArgumentException("Uno de los dos equipos/jugadores debe alcanzar {$targetScore} juegos.");
        }

        if ($homeScore > $targetScore || $awayScore > $targetScore) {
            throw new InvalidArgumentException("No se pueden superar los {$targetScore} juegos.");
        }
    }

    public function resolveWinnerEntryId(GameMatch $match, int $homeScore, int $awayScore): int
    {
        return $homeScore > $awayScore
            ? $match->home_entry_id
            : $match->away_entry_id;
    }

    public function resolveConflict(
        GameMatch $match,
        int $homeScore,
        int $awayScore,
        User $admin
    ): GameMatch {
        return DB::transaction(function () use ($match, $homeScore, $awayScore, $admin): GameMatch {
            /** @var GameMatch $lockedMatch */
            $lockedMatch = GameMatch::query()
                ->whereKey($match->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($lockedMatch->status !== GameMatchStatus::UNDER_REVIEW) {
                throw new InvalidArgumentException(
                    'Solo se pueden resolver partidos que estén en revisión.'
                );
            }

            $this->validateScores(
                $lockedMatch,
                $homeScore,
                $awayScore,
                GameMatchStatus::VALIDATED->value
            );

            $lockedMatch->update([
                'home_score' => $homeScore,
                'away_score' => $awayScore,
                'winner_entry_id' => $this->resolveWinnerEntryId(
                    $lockedMatch,
                    $homeScore,
                    $awayScore
                ),
                'status' => GameMatchStatus::VALIDATED->value,
                'validated_by' => $admin->id,
            ]);

            return $lockedMatch->refresh();
        });
    }
}
