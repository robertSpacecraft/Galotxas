<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\GameMatchStatus;
use App\Enums\MatchResultReportSide;
use App\Http\Controllers\Concerns\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Resources\MatchResource;
use App\Http\Resources\MatchResultReportResource;
use App\Models\CategoryEntry;
use App\Models\GameMatch;
use App\Models\MatchResultReport;
use App\Models\Player;
use App\Services\MatchResultReportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;

class MatchController extends Controller
{
    use ApiResponse;

    public function show(GameMatch $gameMatch): JsonResponse
    {
        $gameMatch->load([
            'homeEntry.player',
            'homeEntry.team.players',
            'awayEntry.player',
            'awayEntry.team.players',
            'winnerEntry.player',
            'winnerEntry.team.players',
            'venue',
            'round.category.championship.season',
            'resultReports.user',
            'resultReports.player',
            'submittedBy',
            'validatedBy',
        ]);

        return $this->successResponse(
            new MatchResource($gameMatch)
        );
    }

    public function workflow(Request $request, GameMatch $gameMatch): JsonResponse
    {
        $gameMatch->load([
            'homeEntry.player',
            'homeEntry.team.players',
            'awayEntry.player',
            'awayEntry.team.players',
            'winnerEntry.player',
            'winnerEntry.team.players',
            'venue',
            'round.category.championship.season',
            'resultReports.user',
            'resultReports.player',
            'submittedBy',
            'validatedBy',
        ]);

        $user = $request->user();
        $player = $user?->player;

        $userSide = null;
        $participates = false;

        if ($player) {
            $userSide = $this->resolvePlayerSide($gameMatch, $player);
            $participates = $userSide !== null;
        }

        $myReport = null;
        $oppositeReport = null;
        $sameSideReportByTeammate = null;
        $canReport = false;
        $blockedReason = null;

        if ($participates && $userSide !== null) {
            $myReport = $gameMatch->resultReports
                ->first(fn ($report) =>
                    $report->side === $userSide
                    && (int) $report->player_id === (int) $player->id
                );

            $sameSideReportByTeammate = $gameMatch->resultReports
                ->first(fn ($report) =>
                    $report->side === $userSide
                    && (int) $report->player_id !== (int) $player->id
                );

            $oppositeSide = $userSide === MatchResultReportSide::HOME
                ? MatchResultReportSide::AWAY
                : MatchResultReportSide::HOME;

            $oppositeReport = $gameMatch->resultReports
                ->first(fn ($report) => $report->side === $oppositeSide);

            if (
                $gameMatch->status === GameMatchStatus::VALIDATED
                || $gameMatch->status === GameMatchStatus::CANCELLED
                || $gameMatch->status === GameMatchStatus::POSTPONED
            ) {
                $blockedReason = 'match_closed';
            } elseif ($gameMatch->status === GameMatchStatus::UNDER_REVIEW) {
                $blockedReason = 'under_review';
            } else {
                if ($myReport !== null) {
                    $blockedReason = 'already_reported_by_you';
                } elseif ($sameSideReportByTeammate !== null) {
                    $blockedReason = 'already_reported_by_teammate';
                } else {
                    $canReport = true;
                }
            }
        }

        return $this->successResponse([
            'match' => new MatchResource($gameMatch),
            'workflow' => [
                'participates' => $participates,
                'user_side' => $userSide?->value,
                'can_report' => $canReport,
                'blocked_reason' => $blockedReason,
                'my_report' => $myReport ? new MatchResultReportResource($myReport) : null,
                'same_side_report_by_teammate' => $sameSideReportByTeammate ? new MatchResultReportResource($sameSideReportByTeammate) : null,
                'opposite_report' => $oppositeReport ? new MatchResultReportResource($oppositeReport) : null,
                'match_status' => $gameMatch->status?->value,
            ],
        ]);
    }

    public function submitResult(
        Request $request,
        GameMatch $gameMatch,
        MatchResultReportService $matchResultReportService
    ): JsonResponse {
        $validated = $request->validate([
            'home_score' => ['required', 'integer', 'min:0'],
            'away_score' => ['required', 'integer', 'min:0'],
            'comment' => ['nullable', 'string', 'max:2000'],
        ]);

        try {
            $report = $matchResultReportService->submitReport(
                $gameMatch,
                $request->user(),
                $validated['home_score'],
                $validated['away_score'],
                $validated['comment'] ?? null
            );
        } catch (InvalidArgumentException $exception) {
            return $this->errorResponse($exception->getMessage());
        }

        return $this->buildSubmissionResponse($gameMatch, $report);
    }

    public function confirmResult(
        Request $request,
        GameMatch $gameMatch,
        MatchResultReportService $matchResultReportService
    ): JsonResponse {
        $gameMatch->load([
            'homeEntry.player',
            'homeEntry.team.players',
            'awayEntry.player',
            'awayEntry.team.players',
            'resultReports.user',
            'resultReports.player',
        ]);

        $user = $request->user();
        $player = $user?->player;

        if (!$player) {
            return $this->errorResponse('El usuario autenticado no tiene un perfil de jugador asociado.');
        }

        $userSide = $this->resolvePlayerSide($gameMatch, $player);

        if ($userSide === null) {
            return $this->errorResponse('El jugador no participa en este partido.');
        }

        $myReport = $gameMatch->resultReports
            ->first(fn ($report) =>
                $report->side === $userSide
                && (int) $report->player_id === (int) $player->id
            );

        if ($myReport !== null) {
            return $this->errorResponse('Ya has enviado un reporte para este partido.');
        }

        $sameSideReportByTeammate = $gameMatch->resultReports
            ->first(fn ($report) =>
                $report->side === $userSide
                && (int) $report->player_id !== (int) $player->id
            );

        if ($sameSideReportByTeammate !== null) {
            return $this->errorResponse('Tu lado ya ha enviado un reporte para este partido.');
        }

        $oppositeSide = $userSide === MatchResultReportSide::HOME
            ? MatchResultReportSide::AWAY
            : MatchResultReportSide::HOME;

        $oppositeReport = $gameMatch->resultReports
            ->first(fn ($report) => $report->side === $oppositeSide);

        if ($oppositeReport === null) {
            return $this->errorResponse('Todavía no existe un reporte rival para confirmar.');
        }

        try {
            $report = $matchResultReportService->submitReport(
                $gameMatch,
                $user,
                (int) $oppositeReport->home_score,
                (int) $oppositeReport->away_score,
                $request->input('comment')
            );
        } catch (InvalidArgumentException $exception) {
            return $this->errorResponse($exception->getMessage());
        }

        return $this->buildSubmissionResponse($gameMatch, $report);
    }

    protected function buildSubmissionResponse(GameMatch $gameMatch, MatchResultReport $report): JsonResponse
    {
        $gameMatch->refresh()->load([
            'homeEntry.player',
            'homeEntry.team.players',
            'awayEntry.player',
            'awayEntry.team.players',
            'winnerEntry.player',
            'winnerEntry.team.players',
            'venue',
            'round.category.championship.season',
            'resultReports.user',
            'resultReports.player',
            'submittedBy',
            'validatedBy',
        ]);

        $oppositeSide = $report->side === MatchResultReportSide::HOME
            ? MatchResultReportSide::AWAY
            : MatchResultReportSide::HOME;

        $oppositeReport = $gameMatch->resultReports
            ->first(fn ($item) => $item->side === $oppositeSide);

        return $this->successResponse(
            [
                'match' => new MatchResource($gameMatch),
                'report' => new MatchResultReportResource($report->fresh(['user', 'player'])),
                'opposite_report' => $oppositeReport ? new MatchResultReportResource($oppositeReport) : null,
            ],
            $this->resolveResponseMessage($gameMatch)
        );
    }

    protected function resolveResponseMessage(GameMatch $gameMatch): string
    {
        return match ($gameMatch->status) {
            GameMatchStatus::VALIDATED => 'Resultado validado correctamente.',
            GameMatchStatus::UNDER_REVIEW => 'Se ha detectado una discrepancia. El partido queda en revisión.',
            GameMatchStatus::SUBMITTED => 'Resultado enviado correctamente. Pendiente de validación por el rival.',
            default => 'Operación completada correctamente.',
        };
    }

    protected function resolvePlayerSide(GameMatch $gameMatch, Player $player): ?MatchResultReportSide
    {
        if ($this->entryContainsPlayer($gameMatch->homeEntry, $player)) {
            return MatchResultReportSide::HOME;
        }

        if ($this->entryContainsPlayer($gameMatch->awayEntry, $player)) {
            return MatchResultReportSide::AWAY;
        }

        return null;
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
