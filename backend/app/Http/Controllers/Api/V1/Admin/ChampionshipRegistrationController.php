<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Concerns\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Admin\UpdateChampionshipRegistrationRequestStatusRequest;
use App\Http\Resources\ChampionshipRegistrationRequestResource;
use App\Models\Championship;
use App\Models\ChampionshipRegistrationRequest;
use App\Services\ChampionshipRegistrationRequestService;
use Illuminate\Http\JsonResponse;

class ChampionshipRegistrationController extends Controller
{
    use ApiResponse;

    public function index(Championship $championship): JsonResponse
    {
        $requests = ChampionshipRegistrationRequest::query()
            ->with([
                'championship',
                'user',
                'player.user',
                'suggestedCategory',
            ])
            ->where('championship_id', $championship->id)
            ->latest()
            ->get();

        return $this->successResponse(
            ChampionshipRegistrationRequestResource::collection($requests)
        );
    }

    public function updateStatus(
        UpdateChampionshipRegistrationRequestStatusRequest $request,
        Championship $championship,
        ChampionshipRegistrationRequest $registrationRequest,
        ChampionshipRegistrationRequestService $service
    ): JsonResponse {
        if ((int) $registrationRequest->championship_id !== (int) $championship->id) {
            abort(404);
        }

        $status = $request->validated()['status'];

        $updated = $status === 'approved'
            ? $service->approve($registrationRequest)
            : $service->reject($registrationRequest);

        return $this->successResponse(
            new ChampionshipRegistrationRequestResource($updated),
            'Estado de la solicitud actualizado correctamente.'
        );
    }
}
