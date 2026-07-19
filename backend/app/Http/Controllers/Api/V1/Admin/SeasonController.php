<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Concerns\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreSeasonRequest;
use App\Http\Requests\Admin\UpdateSeasonRequest;
use App\Http\Resources\AdminSeasonResource;
use App\Models\Season;
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

    public function store(StoreSeasonRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $season = new Season([
            'name' => $validated['name'],
            'status' => $validated['status'],
            'start_date' => $validated['start_date'] ?? null,
            'end_date' => $validated['end_date'] ?? null,
        ]);
        $season->is_public = (bool) $validated['is_public'];
        $season->save();

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

    public function update(UpdateSeasonRequest $request, Season $season): JsonResponse
    {
        $validated = $request->validated();

        $season->fill([
            'name' => $validated['name'],
            'status' => $validated['status'],
            'start_date' => $validated['start_date'] ?? null,
            'end_date' => $validated['end_date'] ?? null,
        ]);
        $season->is_public = (bool) $validated['is_public'];
        $season->save();

        return $this->successResponse(
            new AdminSeasonResource($season),
            'Temporada actualizada correctamente.'
        );
    }

    public function destroy(Season $season): Response
    {
        $season->delete();

        return response()->noContent();
    }
}
