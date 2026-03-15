<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreChampionshipRequest;
use App\Http\Requests\Admin\UpdateChampionshipRequest;
use App\Models\Championship;
use App\Models\Season;

class ChampionshipController extends Controller
{
    public function index()
    {
        $championships = Championship::with('season')->orderByDesc('id')->get();

        return view('admin.championships.index', compact('championships'));
    }

    public function create(Season $season)
    {
        return view('admin.championships.create', compact('season'));
    }

    public function store(StoreChampionshipRequest $request, Season $season)
    {
        Championship::create([
            'season_id' => $season->id,
            ...$request->validated(),
        ]);

        return redirect()
            ->route('admin.seasons.championships', $season)
            ->with('success', 'Campeonato creado correctamente.');
    }

    public function edit(Championship $championship)
    {
        $championship->load('season');

        return view('admin.championships.edit', compact('championship'));
    }

    public function update(UpdateChampionshipRequest $request, Championship $championship)
    {
        $championship->update($request->validated());

        return redirect()
            ->route('admin.seasons.championships', $championship->season)
            ->with('success', 'Campeonato actualizado correctamente.');
    }

    public function destroy(Championship $championship)
    {
        $season = $championship->season;
        $championship->delete();

        return redirect()
            ->route('admin.seasons.championships', $season)
            ->with('success', 'Campeonato eliminado correctamente.');
    }
}
