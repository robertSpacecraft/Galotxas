<?php

namespace App\Services;

use App\Enums\GameMatchStatus;
use App\Models\GameMatch;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class MatchResultService
{
    private readonly MatchScoreRulesService $scoreRules;

    public function __construct(
        private readonly OfficialResultMutationGuard $mutationGuard,
        ?MatchScoreRulesService $scoreRules = null,
    ) {
        $this->scoreRules = $scoreRules ?? new MatchScoreRulesService;
    }

    public function getTargetScore(GameMatch $match): int
    {
        $match->loadMissing('round.category.championship');

        return $this->scoreRules->targetScore($match->round->category->championship->type);
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

        $match->loadMissing('round.category.championship');
        $this->scoreRules->validate(
            $match->round->category->championship->type,
            $homeScore,
            $awayScore,
        );
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
            $lockedMatch = $this->mutationGuard->lockAndGuardMatch($match);

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

    public function updateFromAdmin(
        GameMatch $match,
        int $expectedCategoryId,
        CarbonInterface $scheduledAt,
        int $venueId,
        string $status,
        ?int $homeScore,
        ?int $awayScore,
        User $admin,
    ): GameMatch {
        return DB::transaction(function () use (
            $match,
            $expectedCategoryId,
            $scheduledAt,
            $venueId,
            $status,
            $homeScore,
            $awayScore,
            $admin,
        ): GameMatch {
            $lockedMatch = $this->mutationGuard->lockAndGuardMatch($match);

            if ((int) $lockedMatch->round->category_id !== $expectedCategoryId) {
                throw new InvalidArgumentException('El partido no pertenece a la categoría indicada.');
            }

            if (in_array($status, [
                GameMatchStatus::SUBMITTED->value,
                GameMatchStatus::VALIDATED->value,
            ], true)) {
                $this->validateScores($lockedMatch, $homeScore, $awayScore, $status);
            }

            $updateData = [
                'scheduled_date' => $scheduledAt,
                'venue_id' => $venueId,
                'status' => $status,
            ];

            if ($status === GameMatchStatus::VALIDATED->value) {
                $updateData += [
                    'home_score' => $homeScore,
                    'away_score' => $awayScore,
                    'winner_entry_id' => $this->resolveWinnerEntryId(
                        $lockedMatch,
                        (int) $homeScore,
                        (int) $awayScore
                    ),
                    'submitted_by' => $lockedMatch->submitted_by ?? $admin->id,
                    'validated_by' => $admin->id,
                ];
            } elseif ($status === GameMatchStatus::SUBMITTED->value) {
                $updateData += [
                    'home_score' => $homeScore,
                    'away_score' => $awayScore,
                    'winner_entry_id' => null,
                    'submitted_by' => $admin->id,
                    'validated_by' => null,
                ];
            } else {
                $updateData += [
                    'home_score' => null,
                    'away_score' => null,
                    'winner_entry_id' => null,
                    'submitted_by' => null,
                    'validated_by' => null,
                ];
            }

            $lockedMatch->update($updateData);

            return $lockedMatch->refresh();
        });
    }

    public function validateExistingResult(GameMatch $match, User $admin): GameMatch
    {
        return DB::transaction(function () use ($match, $admin): GameMatch {
            $lockedMatch = $this->mutationGuard->lockAndGuardMatch($match);

            if ($lockedMatch->home_score === null || $lockedMatch->away_score === null) {
                throw new InvalidArgumentException('No se puede validar un partido sin tanteo oficial.');
            }

            $this->validateScores(
                $lockedMatch,
                $lockedMatch->home_score,
                $lockedMatch->away_score,
                GameMatchStatus::VALIDATED->value
            );

            $lockedMatch->update([
                'winner_entry_id' => $this->resolveWinnerEntryId(
                    $lockedMatch,
                    $lockedMatch->home_score,
                    $lockedMatch->away_score
                ),
                'status' => GameMatchStatus::VALIDATED->value,
                'validated_by' => $admin->id,
            ]);

            return $lockedMatch->refresh();
        });
    }
}
