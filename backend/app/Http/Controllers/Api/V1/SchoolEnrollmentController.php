<?php

namespace App\Http\Controllers\Api\V1;

use App\Exceptions\SchoolEnrollmentUnavailableException;
use App\Http\Controllers\Concerns\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\StoreSchoolEnrollmentRequest;
use App\Services\SchoolEnrollmentService;
use Illuminate\Http\JsonResponse;

class SchoolEnrollmentController extends Controller
{
    use ApiResponse;

    public function store(
        StoreSchoolEnrollmentRequest $request,
        SchoolEnrollmentService $service
    ): JsonResponse {
        $attributes = $request->validated();

        if (filled($attributes['website'] ?? null)) {
            return $this->successResponse(
                message: 'La solicitud de inscripción se ha recibido correctamente.',
                status: 201
            );
        }

        unset($attributes['website']);

        try {
            $service->createPublic(
                $attributes,
                $request->user('sanctum')
            );
        } catch (SchoolEnrollmentUnavailableException $exception) {
            return $this->errorResponse(
                $exception->getMessage(),
                status: 409
            );
        }

        return $this->successResponse(
            message: 'La solicitud de inscripción se ha recibido correctamente.',
            status: 201
        );
    }
}
