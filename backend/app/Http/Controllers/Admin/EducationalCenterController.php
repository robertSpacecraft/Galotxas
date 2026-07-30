<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ListEducationalCenterRequest;
use App\Http\Requests\Admin\StoreEducationalCenterRequest;
use App\Http\Requests\Admin\UpdateEducationalCenterRequest;
use App\Models\EducationalCenter;

class EducationalCenterController extends Controller
{
    public function index(ListEducationalCenterRequest $request)
    {
        $filters = $request->validated();
        $centers = EducationalCenter::query()
            ->withCount('activities')
            ->withMax('activities as last_activity_date', 'activity_date')
            ->when(
                array_key_exists('active', $filters),
                fn ($query) => $query->where(
                    'is_active',
                    (string) $filters['active'] === '1'
                )
            )
            ->when(
                isset($filters['locality']),
                fn ($query) => $query->where('locality', $filters['locality'])
            )
            ->ordered()
            ->paginate(25)
            ->withQueryString();

        return view('admin.school.educational-centers.index', [
            'centers' => $centers,
            'filters' => $filters,
            'localities' => EducationalCenter::query()
                ->select('locality')
                ->distinct()
                ->orderBy('locality')
                ->pluck('locality'),
        ]);
    }

    public function create()
    {
        return view('admin.school.educational-centers.create', [
            'center' => new EducationalCenter,
        ]);
    }

    public function store(StoreEducationalCenterRequest $request)
    {
        $center = EducationalCenter::query()->create($request->validated());

        return redirect()
            ->route('admin.school.educational-centers.show', $center)
            ->with('success', 'Centro educativo creado correctamente.');
    }

    public function show(EducationalCenter $educationalCenter)
    {
        $activities = $educationalCenter
            ->activities()
            ->with('location')
            ->ordered()
            ->get();

        return view('admin.school.educational-centers.show', [
            'center' => $educationalCenter,
            'activities' => $activities,
        ]);
    }

    public function edit(EducationalCenter $educationalCenter)
    {
        return view('admin.school.educational-centers.edit', [
            'center' => $educationalCenter,
        ]);
    }

    public function update(
        UpdateEducationalCenterRequest $request,
        EducationalCenter $educationalCenter
    ) {
        $educationalCenter->update($request->validated());

        return redirect()
            ->route('admin.school.educational-centers.show', $educationalCenter)
            ->with('success', 'Centro educativo actualizado correctamente.');
    }

    public function destroy(EducationalCenter $educationalCenter)
    {
        if ($educationalCenter->isInUse()) {
            return redirect()
                ->route('admin.school.educational-centers.show', $educationalCenter)
                ->with(
                    'error',
                    'No se puede eliminar el centro porque conserva actividades.'
                );
        }

        $educationalCenter->delete();

        return redirect()
            ->route('admin.school.educational-centers.index')
            ->with('success', 'Centro educativo eliminado correctamente.');
    }
}
