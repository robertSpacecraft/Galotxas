<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreSchoolLocationRequest;
use App\Http\Requests\Admin\UpdateSchoolLocationRequest;
use App\Models\SchoolLocation;

class SchoolLocationController extends Controller
{
    public function index()
    {
        $locations = SchoolLocation::query()
            ->withCount(['defaultForPrograms', 'schedules'])
            ->ordered()
            ->get();

        return view('admin.school.locations.index', compact('locations'));
    }

    public function create()
    {
        return view('admin.school.locations.create', [
            'location' => new SchoolLocation,
        ]);
    }

    public function store(StoreSchoolLocationRequest $request)
    {
        SchoolLocation::query()->create($request->validated());

        return redirect()
            ->route('admin.school.locations.index')
            ->with('success', 'Ubicación escolar creada correctamente.');
    }

    public function edit(SchoolLocation $location)
    {
        return view('admin.school.locations.edit', compact('location'));
    }

    public function update(
        UpdateSchoolLocationRequest $request,
        SchoolLocation $location
    ) {
        $location->update($request->validated());

        return redirect()
            ->route('admin.school.locations.index')
            ->with('success', 'Ubicación escolar actualizada correctamente.');
    }

    public function destroy(SchoolLocation $location)
    {
        if ($location->isInUse()) {
            return redirect()
                ->route('admin.school.locations.index')
                ->with(
                    'error',
                    'No se puede eliminar la ubicación porque está asociada a programas u horarios.'
                );
        }

        $location->delete();

        return redirect()
            ->route('admin.school.locations.index')
            ->with('success', 'Ubicación escolar eliminada correctamente.');
    }
}
