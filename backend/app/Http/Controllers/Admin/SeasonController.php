<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreSeasonRequest;
use App\Http\Requests\Admin\UpdateSeasonRequest;
use App\Models\Season;

class SeasonController extends Controller
{
    public function index()
    {
        $seasons = Season::orderByDesc('id')->get();

        return view('admin.seasons.index', compact('seasons'));
    }

    public function create()
    {
        return view('admin.seasons.create');
    }

    public function store(StoreSeasonRequest $request)
    {
        Season::create($request->validated());

        return redirect()
            ->route('admin.seasons.index')
            ->with('success', 'Temporada creada correctamente.');
    }

    public function edit(Season $season)
    {
        return view('admin.seasons.edit', compact('season'));
    }

    public function update(UpdateSeasonRequest $request, Season $season)
    {
        $season->update($request->validated());

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
        $season->load('championships');

        return view('admin.seasons.championships', compact('season'));
    }
}
