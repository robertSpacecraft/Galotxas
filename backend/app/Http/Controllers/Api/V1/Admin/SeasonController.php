<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Concerns\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreSeasonRequest;
use App\Http\Requests\Admin\UpdateSeasonRequest;
use App\Http\Resources\AdminSeasonResource;
use App\Models\Season;
use App\Services\OfficialResultProtectedDeletionService;
use App\Services\SeasonService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

class SeasonController extends Controller
{
    use ApiResponse;

    public function index(): JsonResponse
    {
        $seasons = Season::query()->orderBy('id')->get();

        return $this->successResponse(AdminSeasonResource::collection($seasons));
    }

    public function store(StoreSeasonRequest $request, SeasonService $service): JsonResponse
    {
        $season = $service->create($request->validated());

        return $this->successResponse(
            new AdminSeasonResource($season),
            'Temporada creada correctamente.',
            status: 201
        );
    }

    public function show(Season $season): JsonResponse
    {
        return $this->successResponse(new AdminSeasonResource($season));
    }

    public function update(
        UpdateSeasonRequest $request,
        Season $season,
        SeasonService $service
    ): JsonResponse {
        $season = $service->update($season, $request->validated());

        return $this->successResponse(
            new AdminSeasonResource($season),
            'Temporada actualizada correctamente.'
        );
    }

    public function destroy(
        Season $season,
        OfficialResultProtectedDeletionService $deletions,
    ): Response {
        $deletions->deleteSeason($season);

        return response()->noContent();
    }
}
