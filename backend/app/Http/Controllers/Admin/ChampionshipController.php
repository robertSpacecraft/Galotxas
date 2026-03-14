<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Championship;
use App\Models\Season;
use Illuminate\Http\Request;

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

    public function store(Request $request, Season $season)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', 'in:singles,doubles'],
        ]);

        Championship::create([
            'season_id' => $season->id,
            'name' => $validated['name'],
            'type' => $validated['type'],
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

    public function update(Request $request, Championship $championship)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', 'in:singles,doubles'],
        ]);

        $championship->update($validated);

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
