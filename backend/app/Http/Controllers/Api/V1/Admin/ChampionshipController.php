<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Concerns\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreChampionshipRequest;
use App\Http\Requests\Admin\UpdateChampionshipRequest;
use App\Http\Resources\AdminChampionshipResource;
use App\Models\Championship;
use App\Services\ChampionshipMutationService;
use App\Services\OfficialResultProtectedDeletionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

class ChampionshipController extends Controller
{
    use ApiResponse;

    public function index(): JsonResponse
    {
        $championships = Championship::query()
            ->with('season')
            ->orderBy('id')
            ->get();

        return $this->successResponse(AdminChampionshipResource::collection($championships));
    }

    public function store(StoreChampionshipRequest $request, ChampionshipMutationService $mutations): JsonResponse
    {
        $validated = $request->validated();

        $championship = $mutations->create($validated);
        $championship->load('season');

        return $this->successResponse(
            new AdminChampionshipResource($championship),
            'Campeonato creado correctamente.',
            status: 201
        );
    }

    public function show(Championship $championship): JsonResponse
    {
        $championship->load('season');

        return $this->successResponse(new AdminChampionshipResource($championship));
    }

    public function update(
        UpdateChampionshipRequest $request,
        Championship $championship,
        ChampionshipMutationService $mutations,
    ): JsonResponse {
        $validated = $request->validated();
        $championship = $mutations->update($championship, $validated);
        $championship->load('season');

        return $this->successResponse(
            new AdminChampionshipResource($championship),
            'Campeonato actualizado correctamente.'
        );
    }

    public function destroy(
        Championship $championship,
        OfficialResultProtectedDeletionService $deletions,
    ): Response {
        $deletions->deleteChampionship($championship);

        return response()->noContent();
    }
}
