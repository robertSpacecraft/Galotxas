<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Concerns\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Resources\ChampionshipRankingResource;
use App\Models\Season;
use App\Services\Ranking\BuildSeasonRankingService;
use Illuminate\Http\JsonResponse;

class SeasonRankingController extends Controller
{
    use ApiResponse;

    public function __invoke(
        Season $season,
        BuildSeasonRankingService $rankingService
    ): JsonResponse {
        $ranking = $rankingService->build($season);

        return $this->successResponse(
            ChampionshipRankingResource::collection($ranking)
        );
    }
}
