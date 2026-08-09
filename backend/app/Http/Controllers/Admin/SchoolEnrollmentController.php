<?php

namespace App\Http\Controllers\Admin;

use App\Enums\SchoolEnrollmentStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\AnonymizeSchoolEnrollmentRequest;
use App\Http\Requests\Admin\ApproveSchoolEnrollmentRequest;
use App\Http\Requests\Admin\ListSchoolEnrollmentRequest;
use App\Http\Requests\Admin\PlaceSchoolEnrollmentRetentionHoldRequest;
use App\Http\Requests\Admin\ReassignSchoolEnrollmentLevelRequest;
use App\Http\Requests\Admin\RejectSchoolEnrollmentRequest;
use App\Http\Requests\Admin\ReleaseSchoolEnrollmentRetentionHoldRequest;
use App\Http\Requests\Admin\StoreSchoolEnrollmentRequest;
use App\Http\Requests\Admin\UpdateSchoolEnrollmentRequest;
use App\Http\Requests\Admin\WithdrawSchoolEnrollmentRequest;
use App\Models\SchoolEnrollment;
use App\Models\SchoolLevel;
use App\Models\SchoolProgram;
use App\Services\SchoolEnrollmentService;

class SchoolEnrollmentController extends Controller
{
    public function index(ListSchoolEnrollmentRequest $request)
    {
        $filters = $request->validated();
        $enrollments = SchoolEnrollment::query()
            ->with(['program', 'level'])
            ->when(
                isset($filters['program']),
                fn ($query) => $query->where(
                    'school_program_id',
                    $filters['program']
                )
            )
            ->when(
                isset($filters['level']),
                fn ($query) => $query->where(
                    'school_level_id',
                    $filters['level']
                )
            )
            ->when(
                isset($filters['status']),
                fn ($query) => $query->withStatus($filters['status'])
            )
            ->ordered()
            ->paginate(25)
            ->withQueryString();

        $counts = array_fill_keys(SchoolEnrollmentStatus::values(), 0);
        SchoolEnrollment::query()
            ->selectRaw('status, COUNT(*) as aggregate')
            ->groupBy('status')
            ->get()
            ->each(function (SchoolEnrollment $enrollment) use (&$counts): void {
                $counts[$enrollment->status->value] = (int) $enrollment->aggregate;
            });

        return view('admin.school.enrollments.index', [
            'enrollments' => $enrollments,
            'counts' => $counts,
            'statuses' => SchoolEnrollmentStatus::cases(),
            'programs' => SchoolProgram::query()->ordered()->get(),
            'levels' => SchoolLevel::query()
                ->with('program')
                ->ordered()
                ->get(),
            'filters' => $filters,
        ]);
    }

    public function create()
    {
        return view('admin.school.enrollments.create', [
            'enrollment' => new SchoolEnrollment,
            ...$this->formOptions(),
        ]);
    }

    public function store(
        StoreSchoolEnrollmentRequest $request,
        SchoolEnrollmentService $service
    ) {
        $enrollment = $service->createManual($request->validated());

        return redirect()
            ->route('admin.school.enrollments.show', $enrollment)
            ->with('success', 'Inscripción creada como pendiente.');
    }

    public function show(
        SchoolEnrollment $enrollment,
        SchoolEnrollmentService $service
    ) {
        $enrollment->load([
            'program',
            'level',
            'user',
            'publicIdentityAuthorizations',
            'correctedBy',
            'activatedBy',
            'rejectedBy',
            'withdrawnBy',
            'retentionHoldPlacedBy',
            'retentionHoldReleasedBy',
        ]);

        return view('admin.school.enrollments.show', [
            'enrollment' => $enrollment,
            'availableLevels' => $this->availableLevels($enrollment),
            'canAnonymize' => $service->canAnonymize($enrollment),
        ]);
    }

    public function edit(SchoolEnrollment $enrollment)
    {
        if ($enrollment->isAnonymized()) {
            return redirect()
                ->route('admin.school.enrollments.show', $enrollment)
                ->with('error', 'No se pueden corregir datos de una inscripción anonimizada.');
        }

        $enrollment->load(['program', 'level']);

        return view('admin.school.enrollments.edit', compact('enrollment'));
    }

    public function update(
        UpdateSchoolEnrollmentRequest $request,
        SchoolEnrollment $enrollment,
        SchoolEnrollmentService $service
    ) {
        $service->updateDetails(
            $enrollment,
            $request->validated(),
            $request->user()
        );

        return redirect()
            ->route('admin.school.enrollments.show', $enrollment)
            ->with('success', 'Datos de la inscripción actualizados correctamente.');
    }

    public function approve(
        ApproveSchoolEnrollmentRequest $request,
        SchoolEnrollment $enrollment,
        SchoolEnrollmentService $service
    ) {
        $service->approve(
            $enrollment,
            (int) $request->validated('school_level_id'),
            $request->user()
        );

        return redirect()
            ->route('admin.school.enrollments.show', $enrollment)
            ->with('success', 'Inscripción aprobada y activada correctamente.');
    }

    public function reject(
        RejectSchoolEnrollmentRequest $request,
        SchoolEnrollment $enrollment,
        SchoolEnrollmentService $service
    ) {
        $request->validated();
        $service->reject($enrollment, $request->user());

        return redirect()
            ->route('admin.school.enrollments.show', $enrollment)
            ->with('success', 'Inscripción rechazada correctamente.');
    }

    public function withdraw(
        WithdrawSchoolEnrollmentRequest $request,
        SchoolEnrollment $enrollment,
        SchoolEnrollmentService $service
    ) {
        $request->validated();
        $service->withdraw($enrollment, $request->user());

        return redirect()
            ->route('admin.school.enrollments.show', $enrollment)
            ->with('success', 'La inscripción se ha dado de baja conservando su histórico.');
    }

    public function reassignLevel(
        ReassignSchoolEnrollmentLevelRequest $request,
        SchoolEnrollment $enrollment,
        SchoolEnrollmentService $service
    ) {
        $service->reassignLevel(
            $enrollment,
            (int) $request->validated('school_level_id'),
            $request->user()
        );

        return redirect()
            ->route('admin.school.enrollments.show', $enrollment)
            ->with('success', 'Nivel reasignado correctamente.');
    }

    public function placeRetentionHold(
        PlaceSchoolEnrollmentRetentionHoldRequest $request,
        SchoolEnrollment $enrollment,
        SchoolEnrollmentService $service
    ) {
        $service->placeRetentionHold(
            $enrollment,
            $request->user(),
            $request->validated('retention_hold_reason')
        );

        return redirect()
            ->route('admin.school.enrollments.show', $enrollment)
            ->with('success', 'Suspensión de conservación aplicada correctamente.');
    }

    public function releaseRetentionHold(
        ReleaseSchoolEnrollmentRetentionHoldRequest $request,
        SchoolEnrollment $enrollment,
        SchoolEnrollmentService $service
    ) {
        $request->validated();
        $service->releaseRetentionHold($enrollment, $request->user());

        return redirect()
            ->route('admin.school.enrollments.show', $enrollment)
            ->with('success', 'Suspensión de conservación retirada correctamente.');
    }

    public function anonymize(
        AnonymizeSchoolEnrollmentRequest $request,
        SchoolEnrollment $enrollment,
        SchoolEnrollmentService $service
    ) {
        $request->validated();
        $service->anonymize($enrollment, $request->user());

        return redirect()
            ->route('admin.school.enrollments.show', $enrollment)
            ->with('success', 'Datos personales de la inscripción anonimizados.');
    }

    /**
     * @return array<string, mixed>
     */
    private function formOptions(): array
    {
        return [
            'programs' => SchoolProgram::query()->ordered()->get(),
            'levels' => SchoolLevel::query()
                ->with('program')
                ->where('is_active', true)
                ->ordered()
                ->get(),
        ];
    }

    private function availableLevels(SchoolEnrollment $enrollment)
    {
        return SchoolLevel::query()
            ->where('school_program_id', $enrollment->school_program_id)
            ->where('is_active', true)
            ->ordered()
            ->get();
    }
}
