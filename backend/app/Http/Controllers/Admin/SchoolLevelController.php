<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreSchoolLevelRequest;
use App\Http\Requests\Admin\UpdateSchoolLevelRequest;
use App\Models\SchoolLevel;
use App\Models\SchoolProgram;
use Illuminate\Http\Request;

class SchoolLevelController extends Controller
{
    public function index(Request $request)
    {
        $programs = SchoolProgram::query()->ordered()->get();
        $selectedProgramId = $this->selectedProgramId($request);

        $levels = SchoolLevel::query()
            ->with('program')
            ->withCount('schedules')
            ->when(
                $selectedProgramId !== null,
                fn ($query) => $query->where('school_program_id', $selectedProgramId)
            )
            ->ordered()
            ->get();

        return view('admin.school.levels.index', compact(
            'levels',
            'programs',
            'selectedProgramId'
        ));
    }

    public function create(Request $request)
    {
        $programs = SchoolProgram::query()->ordered()->get();
        $selectedProgramId = $this->selectedProgramId($request);

        return view('admin.school.levels.create', [
            'level' => new SchoolLevel([
                'school_program_id' => $selectedProgramId,
            ]),
            'programs' => $programs,
        ]);
    }

    public function store(StoreSchoolLevelRequest $request)
    {
        SchoolLevel::query()->create($request->validated());

        return redirect()
            ->route('admin.school.levels.index')
            ->with('success', 'Nivel creado correctamente.');
    }

    public function edit(SchoolLevel $level)
    {
        return view('admin.school.levels.edit', [
            'level' => $level,
            'programs' => SchoolProgram::query()->ordered()->get(),
        ]);
    }

    public function update(UpdateSchoolLevelRequest $request, SchoolLevel $level)
    {
        $level->update($request->validated());

        return redirect()
            ->route('admin.school.levels.index')
            ->with('success', 'Nivel actualizado correctamente.');
    }

    public function destroy(SchoolLevel $level)
    {
        if ($level->isInUse()) {
            return redirect()
                ->route('admin.school.levels.index')
                ->with('error', 'No se puede eliminar el nivel porque tiene horarios asociados.');
        }

        $level->delete();

        return redirect()
            ->route('admin.school.levels.index')
            ->with('success', 'Nivel eliminado correctamente.');
    }

    private function selectedProgramId(Request $request): ?int
    {
        $programId = $request->integer('program');

        return $programId > 0 && SchoolProgram::query()->whereKey($programId)->exists()
            ? $programId
            : null;
    }
}
