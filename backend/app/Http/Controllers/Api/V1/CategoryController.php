<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Concerns\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Resources\CategoryPublicResource;
use App\Http\Resources\CategoryRankingResource;
use App\Http\Resources\CategoryScheduleRoundResource;
use App\Models\Category;
use App\Services\Ranking\BuildCategoryRankingService;
use Illuminate\Http\JsonResponse;

class CategoryController extends Controller
{
    use ApiResponse;

    public function show(Category $category): JsonResponse
    {
        abort_unless($category->isEffectivelyPublic(), 404);

        $category->load(['championship.season']);

        return $this->successResponse(
            new CategoryPublicResource($category)
        );
    }

    public function schedule(Category $category): JsonResponse
    {
        abort_unless($category->isEffectivelyPublic(), 404);

        $rounds = $category->rounds()->with([
            'matches.homeEntry.player',
            'matches.homeEntry.team',
            'matches.awayEntry.player',
            'matches.awayEntry.team',
            'matches.venue',
        ])->get();

        return $this->successResponse(
            CategoryScheduleRoundResource::collection($rounds)
        );
    }

    public function standings(
        Category $category,
        BuildCategoryRankingService $rankingService
    ): JsonResponse {
        abort_unless($category->isEffectivelyPublic(), 404);

        $ranking = $rankingService->build($category);

        return $this->successResponse(
            CategoryRankingResource::collection($ranking)
        );
    }
}
