<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreChampionshipRequest;
use App\Http\Requests\Admin\UpdateChampionshipRequest;
use App\Models\Championship;
use App\Models\Season;
use Illuminate\Support\Str;

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
        $validated = $request->validated();

        Championship::create([
            'season_id' => $season->id,
            'name' => $validated['name'],
            'slug' => Str::slug($validated['name']),
            'description' => $validated['description'] ?? null,
            'type' => $validated['type'],
            'start_date' => $validated['start_date'] ?? null,
            'end_date' => $validated['end_date'] ?? null,
            'image_path' => $validated['image_path'] ?? null,
            'status' => $validated['status'] ?? 'pending',
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
        $validated = $request->validated();

        $championship->update([
            'name' => $validated['name'],
            'slug' => Str::slug($validated['name']),
            'description' => $validated['description'] ?? null,
            'type' => $validated['type'],
            'start_date' => $validated['start_date'] ?? null,
            'end_date' => $validated['end_date'] ?? null,
            'image_path' => $validated['image_path'] ?? null,
            'status' => $validated['status'] ?? $championship->status,
        ]);

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
