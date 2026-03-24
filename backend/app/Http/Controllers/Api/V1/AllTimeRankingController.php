<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Concerns\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Resources\AllTimeRankingResource;
use App\Services\Ranking\BuildAllTimeRankingService;
use Illuminate\Http\JsonResponse;

class AllTimeRankingController extends Controller
{
    use ApiResponse;

    public function __invoke(
        BuildAllTimeRankingService $rankingService
    ): JsonResponse {
        $ranking = $rankingService->build();

        return $this->successResponse(
            AllTimeRankingResource::collection($ranking)
        );
    }
}
