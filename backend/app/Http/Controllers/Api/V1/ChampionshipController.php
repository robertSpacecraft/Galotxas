<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Concerns\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Resources\ChampionshipPublicResource;
use App\Models\Championship;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ChampionshipController extends Controller
{
    use ApiResponse;

    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'season_id' => ['nullable', 'integer', 'exists:seasons,id'],
            'type' => ['nullable', 'in:singles,doubles'],
            'status' => ['nullable', 'string'],
            'registration_open' => ['nullable', 'boolean'],
        ]);

        $query = Championship::query()
            ->with('season')
            ->orderByDesc('id');

        if (!empty($validated['season_id'])) {
            $query->where('season_id', $validated['season_id']);
        }

        if (!empty($validated['type'])) {
            $query->where('type', $validated['type']);
        }

        if (!empty($validated['status'])) {
            $query->where('status', $validated['status']);
        }

        $championships = $query->get();

        if (array_key_exists('registration_open', $validated)) {
            $registrationOpen = filter_var($validated['registration_open'], FILTER_VALIDATE_BOOLEAN);

            $championships = $championships->filter(function ($championship) use ($registrationOpen) {
                return $championship->registrationIsOpen() === $registrationOpen;
            })->values();
        }

        return $this->successResponse(
            ChampionshipPublicResource::collection($championships)
        );
    }

    public function show(Championship $championship): JsonResponse
    {
        $championship->load([
            'season',
            'categories',
        ]);

        return $this->successResponse(
            new ChampionshipPublicResource($championship)
        );
    }
}
