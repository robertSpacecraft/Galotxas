<?php

namespace App\Http\Controllers\Admin;

use App\Enums\EducationalActivityStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ListEducationalActivityRequest;
use App\Http\Requests\Admin\StoreEducationalActivityRequest;
use App\Http\Requests\Admin\UpdateEducationalActivityRequest;
use App\Models\EducationalActivity;
use App\Models\EducationalCenter;
use App\Models\SchoolLocation;
use App\Services\EducationalActivityService;

class EducationalActivityController extends Controller
{
    public function index(ListEducationalActivityRequest $request)
    {
        $filters = $request->validated();
        $activities = EducationalActivity::query()
            ->with(['center', 'location'])
            ->when(
                isset($filters['center']),
                fn ($query) => $query->forCenter((int) $filters['center'])
            )
            ->when(
                isset($filters['status']),
                fn ($query) => $query->withStatus($filters['status'])
            )
            ->betweenDates(
                $filters['date_from'] ?? null,
                $filters['date_to'] ?? null
            )
            ->ordered()
            ->paginate(25)
            ->withQueryString();

        return view('admin.school.educational-activities.index', [
            'activities' => $activities,
            'centers' => EducationalCenter::query()->ordered()->get(),
            'statuses' => EducationalActivityStatus::cases(),
            'filters' => $filters,
        ]);
    }

    public function create()
    {
        $selectedCenterId = request()->integer('center') ?: null;

        if (
            $selectedCenterId !== null
            && ! EducationalCenter::query()
                ->active()
                ->whereKey($selectedCenterId)
                ->exists()
        ) {
            $selectedCenterId = null;
        }

        return view('admin.school.educational-activities.create', [
            'activity' => new EducationalActivity,
            'selectedCenterId' => $selectedCenterId,
            ...$this->formOptions(),
        ]);
    }

    public function store(
        StoreEducationalActivityRequest $request,
        EducationalActivityService $service
    ) {
        $activity = $service->create($request->validated());

        return redirect()
            ->route('admin.school.educational-activities.show', $activity)
            ->with('success', 'Actividad creada como planificada.');
    }

    public function show(EducationalActivity $educationalActivity)
    {
        $educationalActivity->load(['center', 'location']);

        return view('admin.school.educational-activities.show', [
            'activity' => $educationalActivity,
        ]);
    }

    public function edit(EducationalActivity $educationalActivity)
    {
        $educationalActivity->load(['center', 'location']);

        return view('admin.school.educational-activities.edit', [
            'activity' => $educationalActivity,
            'selectedCenterId' => $educationalActivity->educational_center_id,
            ...$this->formOptions($educationalActivity),
        ]);
    }

    public function update(
        UpdateEducationalActivityRequest $request,
        EducationalActivity $educationalActivity,
        EducationalActivityService $service
    ) {
        $service->update($educationalActivity, $request->validated());

        return redirect()
            ->route(
                'admin.school.educational-activities.show',
                $educationalActivity
            )
            ->with('success', 'Actividad actualizada correctamente.');
    }

    public function complete(
        EducationalActivity $educationalActivity,
        EducationalActivityService $service
    ) {
        $service->complete($educationalActivity);

        return redirect()
            ->route(
                'admin.school.educational-activities.show',
                $educationalActivity
            )
            ->with('success', 'Actividad completada correctamente.');
    }

    public function cancel(
        EducationalActivity $educationalActivity,
        EducationalActivityService $service
    ) {
        $service->cancel($educationalActivity);

        return redirect()
            ->route(
                'admin.school.educational-activities.show',
                $educationalActivity
            )
            ->with('success', 'Actividad cancelada correctamente.');
    }

    public function destroy(
        EducationalActivity $educationalActivity,
        EducationalActivityService $service
    ) {
        $service->delete($educationalActivity);

        return redirect()
            ->route('admin.school.educational-activities.index')
            ->with('success', 'Actividad planificada eliminada correctamente.');
    }

    /**
     * @return array<string, mixed>
     */
    private function formOptions(
        ?EducationalActivity $activity = null
    ): array {
        return [
            'centers' => EducationalCenter::query()
                ->where(function ($query) use ($activity): void {
                    $query->active();

                    if ($activity !== null) {
                        $query->orWhere(
                            $query->qualifyColumn('id'),
                            $activity->educational_center_id
                        );
                    }
                })
                ->ordered()
                ->get(),
            'locations' => SchoolLocation::query()
                ->where(function ($query) use ($activity): void {
                    $query->active();

                    if ($activity?->school_location_id !== null) {
                        $query->orWhere(
                            $query->qualifyColumn('id'),
                            $activity->school_location_id
                        );
                    }
                })
                ->ordered()
                ->get(),
        ];
    }
}
