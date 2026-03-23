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
        $category->load(['championship.season']);

        return $this->successResponse(
            new CategoryPublicResource($category)
        );
    }

    public function schedule(Category $category): JsonResponse
    {
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
        $ranking = $rankingService->build($category);

        return $this->successResponse(
            CategoryRankingResource::collection($ranking)
        );
    }
}
