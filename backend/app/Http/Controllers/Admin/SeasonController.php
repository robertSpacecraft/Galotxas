<?php

namespace App\Http\Controllers\Admin;

use App\Enums\SeasonStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreSeasonRequest;
use App\Http\Requests\Admin\UpdateSeasonRequest;
use App\Models\Season;
use App\Services\Ranking\BuildSeasonRankingService;

class SeasonController extends Controller
{
    public function index()
    {
        $seasons = Season::orderByDesc('id')->get();

        return view('admin.seasons.index', compact('seasons'));
    }

    public function create()
    {
        return view('admin.seasons.create', [
            'season' => new Season,
            'statusOptions' => SeasonStatus::cases(),
            'defaultStatus' => SeasonStatus::PLANNED->value,
        ]);
    }

    public function store(StoreSeasonRequest $request)
    {
        $validated = $request->validated();

        Season::create([
            'name' => $validated['name'],
            'status' => $validated['status'],
            'start_date' => $validated['start_date'] ?? null,
            'end_date' => $validated['end_date'] ?? null,
        ]);

        return redirect()
            ->route('admin.seasons.index')
            ->with('success', 'Temporada creada correctamente.');
    }

    public function show(Season $season, BuildSeasonRankingService $rankingService)
    {
        $season->load('championships');

        $seasonRanking = $rankingService->build($season);

        return view('admin.seasons.show', [
            'season' => $season,
            'seasonRanking' => $seasonRanking,
        ]);
    }

    public function edit(Season $season)
    {
        return view('admin.seasons.edit', [
            'season' => $season,
            'statusOptions' => SeasonStatus::cases(),
            'defaultStatus' => SeasonStatus::PLANNED->value,
        ]);
    }

    public function update(UpdateSeasonRequest $request, Season $season)
    {
        $validated = $request->validated();

        $season->update([
            'name' => $validated['name'],
            'status' => $validated['status'],
            'start_date' => $validated['start_date'] ?? null,
            'end_date' => $validated['end_date'] ?? null,
        ]);

        return redirect()
            ->route('admin.seasons.index')
            ->with('success', 'Temporada actualizada.');
    }

    public function destroy(Season $season)
    {
        $season->delete();

        return redirect()
            ->route('admin.seasons.index')
            ->with('success', 'Temporada eliminada.');
    }

    public function championships(Season $season)
    {
        $championships = $season->championships()
            ->orderBy('id')
            ->get();

        return view('admin.seasons.championships', [
            'season' => $season,
            'championships' => $championships,
        ]);
    }
}
