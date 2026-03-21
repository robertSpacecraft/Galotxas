<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Enums\GameMatchStatus;
use App\Http\Controllers\Concerns\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Resources\MatchResource;
use App\Http\Resources\MatchResultReportResource;
use App\Models\GameMatch;
use App\Services\MatchResultService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;

class MatchController extends Controller
{
    use ApiResponse;

    public function underReview(): JsonResponse
    {
        $matches = GameMatch::query()
            ->where('status', GameMatchStatus::UNDER_REVIEW->value)
            ->with([
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
            ])
            ->orderByDesc('scheduled_date')
            ->get();

        return $this->successResponse(
            MatchResource::collection($matches)
        );
    }

    public function showConflict(GameMatch $gameMatch): JsonResponse
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

        return $this->successResponse([
            'match' => new MatchResource($gameMatch),
            'reports' => MatchResultReportResource::collection($gameMatch->resultReports),
            'is_under_review' => $gameMatch->status === GameMatchStatus::UNDER_REVIEW,
        ]);
    }

    public function resolveConflict(
        Request $request,
        GameMatch $gameMatch,
        MatchResultService $matchResultService
    ): JsonResponse {
        $validated = $request->validate([
            'home_score' => ['required', 'integer', 'min:0'],
            'away_score' => ['required', 'integer', 'min:0'],
        ]);

        try {
            $matchResultService->validateScores(
                $gameMatch,
                $validated['home_score'],
                $validated['away_score'],
                GameMatchStatus::VALIDATED->value
            );
        } catch (InvalidArgumentException $exception) {
            return $this->errorResponse($exception->getMessage());
        }

        $gameMatch->update([
            'home_score' => $validated['home_score'],
            'away_score' => $validated['away_score'],
            'winner_entry_id' => $matchResultService->resolveWinnerEntryId(
                $gameMatch,
                $validated['home_score'],
                $validated['away_score']
            ),
            'status' => GameMatchStatus::VALIDATED->value,
            'validated_by' => $request->user()->id,
        ]);

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

        return $this->successResponse(
            [
                'match' => new MatchResource($gameMatch),
                'reports' => MatchResultReportResource::collection($gameMatch->resultReports),
            ],
            'Conflicto resuelto y resultado validado correctamente.'
        );
    }

    public function validateResult(
        Request $request,
        GameMatch $gameMatch,
        MatchResultService $matchResultService
    ): JsonResponse {
        if ($gameMatch->home_score === null || $gameMatch->away_score === null) {
            return $this->errorResponse('No se puede validar un partido sin tanteo oficial.');
        }

        try {
            $matchResultService->validateScores(
                $gameMatch,
                $gameMatch->home_score,
                $gameMatch->away_score,
                GameMatchStatus::VALIDATED->value
            );
        } catch (InvalidArgumentException $exception) {
            return $this->errorResponse($exception->getMessage());
        }

        $gameMatch->update([
            'winner_entry_id' => $matchResultService->resolveWinnerEntryId(
                $gameMatch,
                (int) $gameMatch->home_score,
                (int) $gameMatch->away_score
            ),
            'status' => GameMatchStatus::VALIDATED->value,
            'validated_by' => $request->user()->id,
        ]);

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

        return $this->successResponse(
            [
                'match' => new MatchResource($gameMatch),
            ],
            'Resultado validado correctamente.'
        );
    }
}
