<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreSchoolProgramRequest;
use App\Http\Requests\Admin\UpdateSchoolProgramRequest;
use App\Models\SchoolLocation;
use App\Models\SchoolProgram;
use App\Services\SchoolProgramService;

class SchoolProgramController extends Controller
{
    public function index()
    {
        $programs = SchoolProgram::query()
            ->with('defaultLocation')
            ->withCount(['levels', 'enrollments'])
            ->ordered()
            ->get();

        return view('admin.school.programs.index', compact('programs'));
    }

    public function create()
    {
        return view('admin.school.programs.create', [
            'program' => new SchoolProgram,
            'locations' => $this->locations(),
        ]);
    }

    public function store(
        StoreSchoolProgramRequest $request,
        SchoolProgramService $service
    ) {
        $service->create($request->validated());

        return redirect()
            ->route('admin.school.programs.index')
            ->with('success', 'Programa de Escuela creado correctamente.');
    }

    public function edit(SchoolProgram $program)
    {
        return view('admin.school.programs.edit', [
            'program' => $program,
            'locations' => $this->locations(),
        ]);
    }

    public function update(
        UpdateSchoolProgramRequest $request,
        SchoolProgram $program,
        SchoolProgramService $service
    ) {
        $service->update($program, $request->validated());

        return redirect()
            ->route('admin.school.programs.index')
            ->with('success', 'Programa de Escuela actualizado correctamente.');
    }

    public function destroy(SchoolProgram $program)
    {
        if ($program->isInUse()) {
            return redirect()
                ->route('admin.school.programs.index')
                ->with(
                    'error',
                    'No se puede eliminar el programa porque tiene niveles o inscripciones asociadas.'
                );
        }

        $program->delete();

        return redirect()
            ->route('admin.school.programs.index')
            ->with('success', 'Programa de Escuela eliminado correctamente.');
    }

    private function locations()
    {
        return SchoolLocation::query()->ordered()->get();
    }
}
