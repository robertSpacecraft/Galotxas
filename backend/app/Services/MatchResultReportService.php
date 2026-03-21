<?php

namespace App\Services;

use App\Enums\GameMatchStatus;
use App\Enums\MatchResultReportSide;
use App\Enums\MatchResultReportStatus;
use App\Models\CategoryEntry;
use App\Models\GameMatch;
use App\Models\MatchResultReport;
use App\Models\Player;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class MatchResultReportService
{
    public function __construct(
        protected MatchResultService $matchResultService
    ) {
    }

    public function submitReport(
        GameMatch $match,
        User $user,
        int $homeScore,
        int $awayScore,
        ?string $comment = null
    ): MatchResultReport {
        $player = $user->player;

        if (!$player) {
            throw new InvalidArgumentException('El usuario autenticado no tiene un perfil de jugador asociado.');
        }

        $this->matchResultService->validateScores(
            $match,
            $homeScore,
            $awayScore,
            GameMatchStatus::SUBMITTED->value
        );

        return DB::transaction(function () use ($match, $user, $player, $homeScore, $awayScore, $comment) {
            /** @var GameMatch $lockedMatch */
            $lockedMatch = GameMatch::query()
                ->whereKey($match->id)
                ->lockForUpdate()
                ->firstOrFail();

            $lockedMatch->load([
                'homeEntry.player',
                'homeEntry.team.players',
                'awayEntry.player',
                'awayEntry.team.players',
            ]);

            $this->assertMatchAllowsReports($lockedMatch);

            $side = $this->resolvePlayerSide($lockedMatch, $player);

            /** @var MatchResultReport|null $sameSideReport */
            $sameSideReport = MatchResultReport::query()
                ->where('game_match_id', $lockedMatch->id)
                ->where('side', $side->value)
                ->first();

            $oppositeSide = $side === MatchResultReportSide::HOME
                ? MatchResultReportSide::AWAY
                : MatchResultReportSide::HOME;

            /** @var MatchResultReport|null $oppositeReport */
            $oppositeReport = MatchResultReport::query()
                ->where('game_match_id', $lockedMatch->id)
                ->where('side', $oppositeSide->value)
                ->first();

            if ($sameSideReport && (int) $sameSideReport->player_id !== (int) $player->id) {
                throw new InvalidArgumentException('Tu lado ya ha enviado un reporte para este partido.');
            }

            try {
                /** @var MatchResultReport $report */
                $report = MatchResultReport::query()->updateOrCreate(
                    [
                        'game_match_id' => $lockedMatch->id,
                        'side' => $side->value,
                    ],
                    [
                        'user_id' => $user->id,
                        'player_id' => $player->id,
                        'home_score' => $homeScore,
                        'away_score' => $awayScore,
                        'status' => MatchResultReportStatus::SUBMITTED->value,
                        'comment' => $comment,
                    ]
                );
            } catch (QueryException $exception) {
                throw new InvalidArgumentException('Se ha producido un conflicto al guardar el reporte. Inténtalo de nuevo.');
            }

            // Releer reporte opuesto tras guardar, por si cambió dentro de la misma ventana lógica
            $oppositeReport = MatchResultReport::query()
                ->where('game_match_id', $lockedMatch->id)
                ->where('side', $oppositeSide->value)
                ->first();

            if (!$oppositeReport) {
                $lockedMatch->update([
                    'status' => GameMatchStatus::SUBMITTED->value,
                    'submitted_by' => $user->id,
                    'validated_by' => null,
                    'home_score' => null,
                    'away_score' => null,
                    'winner_entry_id' => null,
                ]);

                return $report->fresh(['user', 'player']);
            }

            if ($report->hasSameScoresAs($oppositeReport)) {
                $report->update([
                    'status' => MatchResultReportStatus::VALIDATED->value,
                ]);

                $oppositeReport->update([
                    'status' => MatchResultReportStatus::VALIDATED->value,
                ]);

                $lockedMatch->update([
                    'home_score' => $report->home_score,
                    'away_score' => $report->away_score,
                    'winner_entry_id' => $this->matchResultService->resolveWinnerEntryId(
                        $lockedMatch,
                        (int) $report->home_score,
                        (int) $report->away_score
                    ),
                    'status' => GameMatchStatus::VALIDATED->value,
                    'submitted_by' => $oppositeReport->user_id,
                    'validated_by' => $user->id,
                ]);

                return $report->fresh(['user', 'player']);
            }

            $report->update([
                'status' => MatchResultReportStatus::CONFLICT->value,
            ]);

            $oppositeReport->update([
                'status' => MatchResultReportStatus::CONFLICT->value,
            ]);

            $lockedMatch->update([
                'home_score' => null,
                'away_score' => null,
                'winner_entry_id' => null,
                'status' => GameMatchStatus::UNDER_REVIEW->value,
                'submitted_by' => null,
                'validated_by' => null,
            ]);

            return $report->fresh(['user', 'player']);
        });
    }

    protected function assertMatchAllowsReports(GameMatch $match): void
    {
        if (
            $match->status === GameMatchStatus::VALIDATED
            || $match->status === GameMatchStatus::CANCELLED
            || $match->status === GameMatchStatus::POSTPONED
            || $match->status === GameMatchStatus::UNDER_REVIEW
        ) {
            throw new InvalidArgumentException('Este partido no admite nuevos reportes.');
        }
    }

    protected function resolvePlayerSide(GameMatch $match, Player $player): MatchResultReportSide
    {
        if ($this->entryContainsPlayer($match->homeEntry, $player)) {
            return MatchResultReportSide::HOME;
        }

        if ($this->entryContainsPlayer($match->awayEntry, $player)) {
            return MatchResultReportSide::AWAY;
        }

        throw new InvalidArgumentException('El jugador no participa en este partido.');
    }

    protected function entryContainsPlayer(?CategoryEntry $entry, Player $player): bool
    {
        if (!$entry) {
            return false;
        }

        if ($entry->entry_type === 'player') {
            return (int) $entry->player_id === (int) $player->id;
        }

        if ($entry->entry_type === 'team') {
            return $entry->team !== null
                && $entry->team->players->contains(
                    fn (Player $teamPlayer): bool => (int) $teamPlayer->id === (int) $player->id
                );
        }

        return false;
    }
}
