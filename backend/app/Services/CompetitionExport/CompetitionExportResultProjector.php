<?php

namespace App\Services\CompetitionExport;

use App\Enums\ChampionshipType;
use App\Enums\GameMatchStatus;
use App\Exceptions\CategoryCompetitionExportException;
use App\Models\GameMatch;
use App\Services\MatchScoreRulesService;
use InvalidArgumentException;

final readonly class CompetitionExportResultProjector
{
    public function __construct(
        private MatchScoreRulesService $scoreRules,
    ) {}

    public function project(
        GameMatch $match,
        ChampionshipType|string $championshipType,
    ): ?string {
        $status = $match->status instanceof GameMatchStatus
            ? $match->status
            : GameMatchStatus::tryFrom((string) $match->status);

        return match ($status) {
            GameMatchStatus::SCHEDULED,
            GameMatchStatus::SUBMITTED,
            GameMatchStatus::UNDER_REVIEW => null,
            GameMatchStatus::POSTPONED => 'Aplazado',
            GameMatchStatus::CANCELLED => 'Cancelado',
            GameMatchStatus::VALIDATED => $this->validatedResult($match, $championshipType),
            null => throw new CategoryCompetitionExportException(
                CategoryCompetitionExportException::AMBIGUOUS_STRUCTURE
            ),
        };
    }

    private function validatedResult(
        GameMatch $match,
        ChampionshipType|string $championshipType,
    ): string {
        try {
            $this->scoreRules->validate(
                $championshipType,
                $match->home_score,
                $match->away_score,
            );
        } catch (InvalidArgumentException) {
            throw new CategoryCompetitionExportException(
                CategoryCompetitionExportException::INVALID_RESULT
            );
        }

        $homeScore = (int) $match->home_score;
        $awayScore = (int) $match->away_score;
        $winnerEntryId = $match->winner_entry_id === null
            ? null
            : (int) $match->winner_entry_id;
        $expectedWinnerEntryId = $homeScore > $awayScore
            ? (int) $match->home_entry_id
            : (int) $match->away_entry_id;

        if (
            $winnerEntryId === null
            || ! in_array($winnerEntryId, [
                (int) $match->home_entry_id,
                (int) $match->away_entry_id,
            ], true)
            || $winnerEntryId !== $expectedWinnerEntryId
        ) {
            throw new CategoryCompetitionExportException(
                CategoryCompetitionExportException::INVALID_RESULT
            );
        }

        return $homeScore.'-'.$awayScore;
    }
}
