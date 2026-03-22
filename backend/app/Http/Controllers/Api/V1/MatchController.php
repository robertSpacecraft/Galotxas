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
use App\Http\Resources\MatchRescheduleRequestResource;
use App\Services\MatchRescheduleRequestService;

class MatchController extends Controller
{
    use ApiResponse;

    public function show(GameMatch $gameMatch): JsonResponse
    {
        $gameMatch->load([
            'homeEntry.player.user',
            'homeEntry.team.players.user',
            'awayEntry.player.user',
            'awayEntry.team.players.user',
            'winnerEntry.player.user',
            'winnerEntry.team.players.user',
            'venue',
            'round.category.championship.season',
            'resultReports.user',
            'resultReports.player.user',
            'submittedBy',
            'validatedBy',
        ]);

        return $this->successResponse(
            new MatchResource($gameMatch)
        );
    }

    public function myMatches(Request $request): JsonResponse
    {
        $user = $request->user();
        $player = $user?->player;

        if (!$player) {
            return $this->errorResponse('El usuario autenticado no tiene un perfil de jugador asociado.');
        }

        $validated = $request->validate([
            'status' => ['nullable', 'string', 'in:scheduled,submitted,validated,under_review,postponed,cancelled'],
            'category_id' => ['nullable', 'integer', 'exists:categories,id'],
            'championship_id' => ['nullable', 'integer', 'exists:championships,id'],
        ]);

        $query = $this->basePlayerMatchesQuery($player);

        if (!empty($validated['status'])) {
            $query->where('status', $validated['status']);
        }

        if (!empty($validated['category_id'])) {
            $query->whereHas('round', function ($roundQuery) use ($validated) {
                $roundQuery->where('category_id', $validated['category_id']);
            });
        }

        if (!empty($validated['championship_id'])) {
            $query->whereHas('round.category', function ($categoryQuery) use ($validated) {
                $categoryQuery->where('championship_id', $validated['championship_id']);
            });
        }

        $matches = $query
            ->orderBy('scheduled_date')
            ->orderBy('id')
            ->get();

        return $this->successResponse(
            MatchResource::collection($matches)
        );
    }

    public function pendingActions(Request $request): JsonResponse
    {
        $user = $request->user();
        $player = $user?->player;

        if (!$player) {
            return $this->errorResponse('El usuario autenticado no tiene un perfil de jugador asociado.');
        }

        $matches = $this->basePlayerMatchesQuery($player)
            ->orderBy('scheduled_date')
            ->orderBy('id')
            ->get();

        $pendingReport = collect();
        $pendingConfirmation = collect();
        $underReview = collect();
        $upcoming = collect();

        foreach ($matches as $match) {
            $userSide = $this->resolvePlayerSide($match, $player);

            if ($userSide === null) {
                continue;
            }

            $myReport = $match->resultReports->first(fn ($report) =>
                $report->side === $userSide
                && (int) $report->player_id === (int) $player->id
            );

            $sameSideReportByTeammate = $match->resultReports->first(fn ($report) =>
                $report->side === $userSide
                && (int) $report->player_id !== (int) $player->id
            );

            $oppositeSide = $userSide === MatchResultReportSide::HOME
                ? MatchResultReportSide::AWAY
                : MatchResultReportSide::HOME;

            $oppositeReport = $match->resultReports->first(fn ($report) =>
                $report->side === $oppositeSide
            );

            if ($match->status === GameMatchStatus::UNDER_REVIEW) {
                $underReview->push($match);
            }

            if (
                $match->scheduled_date !== null
                && $match->scheduled_date->isFuture()
                && in_array($match->status?->value, [
                    GameMatchStatus::SCHEDULED->value,
                    GameMatchStatus::SUBMITTED->value,
                    GameMatchStatus::UNDER_REVIEW->value,
                ], true)
            ) {
                $upcoming->push($match);
            }

            if (
                in_array($match->status?->value, [
                    GameMatchStatus::SCHEDULED->value,
                    GameMatchStatus::SUBMITTED->value,
                ], true)
                && $myReport === null
                && $sameSideReportByTeammate === null
            ) {
                $pendingReport->push($match);

                if ($oppositeReport !== null) {
                    $pendingConfirmation->push($match);
                }
            }
        }

        return $this->successResponse([
            'pending_report' => MatchResource::collection($pendingReport->values()),
            'pending_confirmation' => MatchResource::collection($pendingConfirmation->values()),
            'under_review' => MatchResource::collection($underReview->values()),
            'upcoming' => MatchResource::collection($upcoming->take(10)->values()),
            'counts' => [
                'pending_report' => $pendingReport->count(),
                'pending_confirmation' => $pendingConfirmation->count(),
                'under_review' => $underReview->count(),
                'upcoming' => $upcoming->count(),
            ],
        ]);
    }

    public function workflow(Request $request, GameMatch $gameMatch): JsonResponse
    {
        $gameMatch->load([
            'homeEntry.player.user',
            'homeEntry.team.players.user',
            'awayEntry.player.user',
            'awayEntry.team.players.user',
            'winnerEntry.player.user',
            'winnerEntry.team.players.user',
            'venue',
            'round.category.championship.season',
            'resultReports.user',
            'resultReports.player.user',
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
            'homeEntry.player.user',
            'homeEntry.team.players.user',
            'awayEntry.player.user',
            'awayEntry.team.players.user',
            'resultReports.user',
            'resultReports.player.user',
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
            'homeEntry.player.user',
            'homeEntry.team.players.user',
            'awayEntry.player.user',
            'awayEntry.team.players.user',
            'winnerEntry.player.user',
            'winnerEntry.team.players.user',
            'venue',
            'round.category.championship.season',
            'resultReports.user',
            'resultReports.player.user',
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
                'report' => new MatchResultReportResource($report->fresh(['user', 'player.user'])),
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

    protected function basePlayerMatchesQuery(Player $player)
    {
        return GameMatch::query()
            ->with([
                'homeEntry.player.user',
                'homeEntry.team.players.user',
                'awayEntry.player.user',
                'awayEntry.team.players.user',
                'winnerEntry.player.user',
                'winnerEntry.team.players.user',
                'venue',
                'round.category.championship.season',
                'resultReports.user',
                'resultReports.player.user',
                'submittedBy',
                'validatedBy',
            ])
            ->where(function ($query) use ($player) {
                $query
                    ->whereHas('homeEntry', function ($subQuery) use ($player) {
                        $subQuery->where(function ($entryQuery) use ($player) {
                            $entryQuery
                                ->where(function ($playerEntryQuery) use ($player) {
                                    $playerEntryQuery
                                        ->where('entry_type', 'player')
                                        ->where('player_id', $player->id);
                                })
                                ->orWhere(function ($teamEntryQuery) use ($player) {
                                    $teamEntryQuery
                                        ->where('entry_type', 'team')
                                        ->whereHas('team.players', function ($teamPlayersQuery) use ($player) {
                                            $teamPlayersQuery->where('players.id', $player->id);
                                        });
                                });
                        });
                    })
                    ->orWhereHas('awayEntry', function ($subQuery) use ($player) {
                        $subQuery->where(function ($entryQuery) use ($player) {
                            $entryQuery
                                ->where(function ($playerEntryQuery) use ($player) {
                                    $playerEntryQuery
                                        ->where('entry_type', 'player')
                                        ->where('player_id', $player->id);
                                })
                                ->orWhere(function ($teamEntryQuery) use ($player) {
                                    $teamEntryQuery
                                        ->where('entry_type', 'team')
                                        ->whereHas('team.players', function ($teamPlayersQuery) use ($player) {
                                            $teamPlayersQuery->where('players.id', $player->id);
                                        });
                                });
                        });
                    });
            });
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

    public function rescheduleWorkflow(Request $request, GameMatch $gameMatch): JsonResponse
    {
        $gameMatch->load([
            'homeEntry.player.user',
            'homeEntry.team.players.user',
            'awayEntry.player.user',
            'awayEntry.team.players.user',
            'venue',
            'round.category.championship.season',
            'rescheduleRequests.user',
            'rescheduleRequests.player.user',
            'rescheduleRequests.requestedVenue',
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

        $myRequest = $gameMatch->rescheduleRequests->first(fn ($item) =>
            $item->side === $userSide
            && (int) $item->player_id === (int) $player->id
        );

        $sameSideRequestByTeammate = $gameMatch->rescheduleRequests->first(fn ($item) =>
            $item->side === $userSide
            && (int) $item->player_id !== (int) $player->id
        );

        $oppositeSide = $userSide === MatchResultReportSide::HOME
            ? MatchResultReportSide::AWAY
            : MatchResultReportSide::HOME;

        $oppositeRequest = $gameMatch->rescheduleRequests->first(fn ($item) =>
            $item->side === $oppositeSide
        );

        $canSubmit = false;
        $canConfirm = false;
        $blockedReason = null;

        if (
            $gameMatch->status === GameMatchStatus::VALIDATED
            || $gameMatch->status === GameMatchStatus::CANCELLED
            || $gameMatch->status === GameMatchStatus::POSTPONED
            || $gameMatch->status === GameMatchStatus::UNDER_REVIEW
        ) {
            $blockedReason = 'match_closed_for_reschedule';
        } elseif ($myRequest !== null) {
            $blockedReason = 'already_requested_by_you';
        } elseif ($sameSideRequestByTeammate !== null) {
            $blockedReason = 'already_requested_by_teammate';
        } elseif ($oppositeRequest !== null) {
            $canConfirm = true;
        } else {
            $canSubmit = true;
        }

        return $this->successResponse([
            'match' => new MatchResource($gameMatch),
            'workflow' => [
                'user_side' => $userSide->value,
                'can_submit' => $canSubmit,
                'can_confirm' => $canConfirm,
                'blocked_reason' => $blockedReason,
                'my_request' => $myRequest ? new MatchRescheduleRequestResource($myRequest) : null,
                'same_side_request_by_teammate' => $sameSideRequestByTeammate ? new MatchRescheduleRequestResource($sameSideRequestByTeammate) : null,
                'opposite_request' => $oppositeRequest ? new MatchRescheduleRequestResource($oppositeRequest) : null,
                'match_status' => $gameMatch->status?->value,
            ],
        ]);
    }

    public function requestReschedule(
        Request $request,
        GameMatch $gameMatch,
        MatchRescheduleRequestService $matchRescheduleRequestService
    ): JsonResponse {
        $validated = $request->validate([
            'scheduled_date' => ['required', 'date'],
            'scheduled_time' => ['required', 'date_format:H:i'],
            'venue_id' => ['required', 'exists:venues,id'],
            'comment' => ['nullable', 'string', 'max:2000'],
        ]);

        try {
            $rescheduleRequest = $matchRescheduleRequestService->submitRequest(
                $gameMatch,
                $request->user(),
                $validated['scheduled_date'],
                $validated['scheduled_time'],
                (int) $validated['venue_id'],
                $validated['comment'] ?? null
            );
        } catch (InvalidArgumentException $exception) {
            return $this->errorResponse($exception->getMessage());
        }

        return $this->successResponse(
            new MatchRescheduleRequestResource($rescheduleRequest),
            'Solicitud de reprogramación enviada correctamente.'
        );
    }

    public function confirmReschedule(
        Request $request,
        GameMatch $gameMatch,
        MatchRescheduleRequestService $matchRescheduleRequestService
    ): JsonResponse {
        try {
            $rescheduleRequest = $matchRescheduleRequestService->confirmRequest(
                $gameMatch,
                $request->user()
            );
        } catch (InvalidArgumentException $exception) {
            return $this->errorResponse($exception->getMessage());
        }

        $gameMatch->refresh()->load([
            'homeEntry.player.user',
            'homeEntry.team.players.user',
            'awayEntry.player.user',
            'awayEntry.team.players.user',
            'venue',
            'round.category.championship.season',
            'rescheduleRequests.user',
            'rescheduleRequests.player.user',
            'rescheduleRequests.requestedVenue',
        ]);

        return $this->successResponse(
            [
                'match' => new MatchResource($gameMatch),
                'request' => new MatchRescheduleRequestResource($rescheduleRequest),
            ],
            'Reprogramación confirmada correctamente.'
        );
    }
}
