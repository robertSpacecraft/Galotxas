<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Concerns\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\SubmitChampionshipRegistrationRequest;
use App\Http\Resources\ChampionshipRegistrationRequestResource;
use App\Models\Championship;
use App\Models\ChampionshipRegistrationRequest;
use App\Services\ChampionshipRegistrationRequestService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;

class ChampionshipRegistrationController extends Controller
{
    use ApiResponse;

    public function show(Request $request, Championship $championship): JsonResponse
    {
        $user = $request->user();
        $player = $user?->player;

        if (!$player) {
            return $this->errorResponse('El usuario autenticado no tiene un perfil de jugador asociado.');
        }

        $registrationRequest = ChampionshipRegistrationRequest::query()
            ->with([
                'championship',
                'user',
                'player.user',
                'suggestedCategory',
            ])
            ->where('championship_id', $championship->id)
            ->where('player_id', $player->id)
            ->first();

        return $this->successResponse([
            'championship_id' => $championship->id,
            'championship_name' => $championship->name,
            'registration_is_open' => $championship->registrationIsOpen(),
            'registration_status' => $championship->registration_status?->value,
            'registration_window' => [
                'starts_at' => $championship->registration_starts_at?->toISOString(),
                'ends_at' => $championship->registration_ends_at?->toISOString(),
            ],
            'request' => $registrationRequest ? new ChampionshipRegistrationRequestResource($registrationRequest) : null,
        ]);
    }

    public function submit(
        SubmitChampionshipRegistrationRequest $request,
        Championship $championship,
        ChampionshipRegistrationRequestService $service
    ): JsonResponse {
        $validated = $request->validated();

        try {
            $registrationRequest = $service->submit(
                $championship,
                $request->user(),
                $validated['suggested_category_id'] ?? null,
                $validated['comment'] ?? null
            );
        } catch (InvalidArgumentException $exception) {
            return $this->errorResponse($exception->getMessage());
        }

        return $this->successResponse(
            new ChampionshipRegistrationRequestResource($registrationRequest),
            'Solicitud de inscripción enviada correctamente.'
        );
    }
}
