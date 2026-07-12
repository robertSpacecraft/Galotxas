<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreVenueRequest;
use App\Http\Requests\Admin\UpdateVenueRequest;
use App\Models\Venue;

class VenueController extends Controller
{
    public function index()
    {
        $venues = Venue::query()
            ->withCount(['matches', 'rescheduleRequests'])
            ->orderBy('name')
            ->get();

        return view('admin.venues.index', compact('venues'));
    }

    public function create()
    {
        return view('admin.venues.create');
    }

    public function store(StoreVenueRequest $request)
    {
        Venue::query()->create($request->validated());

        return redirect()
            ->route('admin.venues.index')
            ->with('success', 'Pista creada correctamente.');
    }

    public function edit(Venue $venue)
    {
        return view('admin.venues.edit', compact('venue'));
    }

    public function update(UpdateVenueRequest $request, Venue $venue)
    {
        $venue->update($request->validated());

        return redirect()
            ->route('admin.venues.index')
            ->with('success', 'Pista actualizada correctamente.');
    }

    public function destroy(Venue $venue)
    {
        if ($venue->isInUse()) {
            return redirect()
                ->route('admin.venues.index')
                ->with('error', 'No se puede eliminar la pista porque está asociada a partidos o solicitudes de reprogramación.');
        }

        $venue->delete();

        return redirect()
            ->route('admin.venues.index')
            ->with('success', 'Pista eliminada correctamente.');
    }
}
