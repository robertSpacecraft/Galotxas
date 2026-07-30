<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Concerns\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Resources\PublicSchoolResource;
use App\Services\SchoolPublicOverviewService;
use Illuminate\Http\JsonResponse;

class SchoolController extends Controller
{
    use ApiResponse;

    public function __invoke(
        SchoolPublicOverviewService $overviewService
    ): JsonResponse {
        $program = $overviewService->get();

        return $this->successResponse(
            $program === null ? null : new PublicSchoolResource($program)
        );
    }
}
