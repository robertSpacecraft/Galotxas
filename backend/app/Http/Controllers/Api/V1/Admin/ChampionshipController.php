<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Concerns\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreChampionshipRequest;
use App\Http\Requests\Admin\UpdateChampionshipRequest;
use App\Http\Resources\AdminChampionshipResource;
use App\Models\Championship;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Str;

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

    public function store(StoreChampionshipRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $championship = new Championship([
            'season_id' => $validated['season_id'],
            'name' => $validated['name'],
            'slug' => Str::slug($validated['name']),
            'description' => $validated['description'] ?? null,
            'type' => $validated['type'],
            'status' => $validated['status'],
            'start_date' => $validated['start_date'] ?? null,
            'end_date' => $validated['end_date'] ?? null,
            'registration_status' => $validated['registration_status'],
            'registration_starts_at' => $validated['registration_starts_at'] ?? null,
            'registration_ends_at' => $validated['registration_ends_at'] ?? null,
        ]);
        $championship->is_public = (bool) $validated['is_public'];
        $championship->save();
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
        Championship $championship
    ): JsonResponse {
        $validated = $request->validated();

        $championship->fill([
            'season_id' => $validated['season_id'],
            'name' => $validated['name'],
            'slug' => Str::slug($validated['name']),
            'description' => $validated['description'] ?? null,
            'type' => $validated['type'],
            'status' => $validated['status'],
            'start_date' => $validated['start_date'] ?? null,
            'end_date' => $validated['end_date'] ?? null,
            'registration_status' => $validated['registration_status'],
            'registration_starts_at' => $validated['registration_starts_at'] ?? null,
            'registration_ends_at' => $validated['registration_ends_at'] ?? null,
        ]);
        $championship->is_public = (bool) $validated['is_public'];
        $championship->save();
        $championship->load('season');

        return $this->successResponse(
            new AdminChampionshipResource($championship),
            'Campeonato actualizado correctamente.'
        );
    }

    public function destroy(Championship $championship): Response
    {
        $championship->delete();

        return response()->noContent();
    }
}
