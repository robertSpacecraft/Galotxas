<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Season;
use Illuminate\Http\Request;

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

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'status' => ['required', 'in:planned,active,finished,cancelled'],
        ]);

        Season::create($validated);

        return redirect()
            ->route('admin.seasons.index')
            ->with('success', 'Temporada creada correctamente.');
    }

    public function edit(Season $season)
    {
        return view('admin.seasons.edit', compact('season'));
    }

    public function update(Request $request, Season $season)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'status' => ['required', 'in:planned,active,finished,cancelled'],
        ]);

        $season->update($validated);

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
}
