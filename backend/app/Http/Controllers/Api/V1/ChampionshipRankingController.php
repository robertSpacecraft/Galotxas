<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Concerns\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Resources\ChampionshipRankingResource;
use App\Models\Championship;
use App\Services\Ranking\BuildChampionshipRankingService;
use Illuminate\Http\JsonResponse;

class ChampionshipRankingController extends Controller
{
    use ApiResponse;

    public function __invoke(
        Championship $championship,
        BuildChampionshipRankingService $rankingService
    ): JsonResponse {
        abort_unless($championship->isEffectivelyPublic(), 404);

        $ranking = $rankingService->build($championship, publicOnly: true);

        return $this->successResponse(
            ChampionshipRankingResource::collection($ranking)
        );
    }
}
