<?php

namespace App\Http\Controllers\Admin;

use App\Enums\SchoolDayOfWeek;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreSchoolScheduleRequest;
use App\Http\Requests\Admin\UpdateSchoolScheduleRequest;
use App\Models\SchoolLevel;
use App\Models\SchoolLocation;
use App\Models\SchoolProgram;
use App\Models\SchoolSchedule;
use Illuminate\Http\Request;

class SchoolScheduleController extends Controller
{
    public function index(Request $request)
    {
        $programs = SchoolProgram::query()->ordered()->get();
        $allLevels = SchoolLevel::query()->with('program')->ordered()->get();
        $selectedProgramId = $this->selectedProgramId($request);
        $selectedLevelId = $this->selectedLevelId($request, $selectedProgramId);

        $schedules = SchoolSchedule::query()
            ->with(['level.program', 'location'])
            ->when(
                $selectedProgramId !== null,
                fn ($query) => $query->whereHas(
                    'level',
                    fn ($levelQuery) => $levelQuery->where(
                        'school_program_id',
                        $selectedProgramId
                    )
                )
            )
            ->when(
                $selectedLevelId !== null,
                fn ($query) => $query->where('school_level_id', $selectedLevelId)
            )
            ->ordered()
            ->get();

        return view('admin.school.schedules.index', compact(
            'schedules',
            'programs',
            'allLevels',
            'selectedProgramId',
            'selectedLevelId'
        ));
    }

    public function create(Request $request)
    {
        $selectedLevelId = $request->integer('level');

        if (! SchoolLevel::query()->whereKey($selectedLevelId)->exists()) {
            $selectedLevelId = null;
        }

        return view('admin.school.schedules.create', [
            'schedule' => new SchoolSchedule([
                'school_level_id' => $selectedLevelId,
            ]),
            ...$this->formOptions(),
        ]);
    }

    public function store(StoreSchoolScheduleRequest $request)
    {
        SchoolSchedule::query()->create($request->validated());

        return redirect()
            ->route('admin.school.schedules.index')
            ->with('success', 'Horario creado correctamente.');
    }

    public function edit(SchoolSchedule $schedule)
    {
        return view('admin.school.schedules.edit', [
            'schedule' => $schedule,
            ...$this->formOptions(),
        ]);
    }

    public function update(
        UpdateSchoolScheduleRequest $request,
        SchoolSchedule $schedule
    ) {
        $schedule->update($request->validated());

        return redirect()
            ->route('admin.school.schedules.index')
            ->with('success', 'Horario actualizado correctamente.');
    }

    public function destroy(SchoolSchedule $schedule)
    {
        $schedule->delete();

        return redirect()
            ->route('admin.school.schedules.index')
            ->with('success', 'Horario eliminado correctamente.');
    }

    /**
     * @return array<string, mixed>
     */
    private function formOptions(): array
    {
        return [
            'levels' => SchoolLevel::query()->with('program')->ordered()->get(),
            'locations' => SchoolLocation::query()->ordered()->get(),
            'dayOptions' => SchoolDayOfWeek::cases(),
        ];
    }

    private function selectedProgramId(Request $request): ?int
    {
        $programId = $request->integer('program');

        return $programId > 0 && SchoolProgram::query()->whereKey($programId)->exists()
            ? $programId
            : null;
    }

    private function selectedLevelId(Request $request, ?int $programId): ?int
    {
        $levelId = $request->integer('level');
        $query = SchoolLevel::query()->whereKey($levelId);

        if ($programId !== null) {
            $query->where('school_program_id', $programId);
        }

        return $levelId > 0 && $query->exists() ? $levelId : null;
    }
}
