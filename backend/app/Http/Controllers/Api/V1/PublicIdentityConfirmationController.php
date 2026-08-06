<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Concerns\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\PublicIdentityTokenRequest;
use App\Services\PublicIdentityAuthorizationService;
use Illuminate\Http\JsonResponse;

class PublicIdentityConfirmationController extends Controller
{
    use ApiResponse;

    public function lookup(
        PublicIdentityTokenRequest $request,
        PublicIdentityAuthorizationService $service
    ): JsonResponse {
        $data = $service->lookup($request->validated('token'));

        return $data === null ? $this->unavailable() : $this->successResponse($data);
    }

    public function confirm(
        PublicIdentityTokenRequest $request,
        PublicIdentityAuthorizationService $service
    ): JsonResponse {
        return $service->confirm($request->validated('token'))
            ? $this->decisionRecorded()
            : $this->unavailable();
    }

    public function deny(
        PublicIdentityTokenRequest $request,
        PublicIdentityAuthorizationService $service
    ): JsonResponse {
        return $service->denyByGuardian($request->validated('token'))
            ? $this->decisionRecorded()
            : $this->unavailable();
    }

    private function unavailable(): JsonResponse
    {
        return $this->errorResponse(
            'El enlace no es válido o ya no está disponible.',
            status: 404
        );
    }

    private function decisionRecorded(): JsonResponse
    {
        return $this->successResponse(
            ['received' => true],
            'La decisión se ha registrado correctamente.'
        );
    }
}
