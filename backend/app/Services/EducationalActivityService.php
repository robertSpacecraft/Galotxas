<?php

namespace App\Services;

use App\Enums\EducationalActivityStatus;
use App\Models\EducationalActivity;
use App\Models\EducationalCenter;
use App\Models\SchoolLocation;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class EducationalActivityService
{
    public const CENTER_ACTIVE_ERROR =
        'El centro educativo debe estar activo para asignarle la actividad.';

    public const LOCATION_ACTIVE_ERROR =
        'La ubicación escolar debe estar activa para asignarla a la actividad.';

    public const TRANSITION_ERROR =
        'Sólo una actividad planificada puede completarse o cancelarse.';

    public const COMPLETION_STUDENTS_ERROR =
        'Indica un número positivo de alumnado previsto antes de completar la actividad.';

    public const DELETE_ERROR =
        'Sólo se pueden eliminar actividades que continúan planificadas.';

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function create(array $attributes): EducationalActivity
    {
        return DB::transaction(function () use ($attributes): EducationalActivity {
            $this->assertActiveCenter((int) $attributes['educational_center_id']);
            $this->assertActiveLocation($attributes['school_location_id'] ?? null);

            $activity = new EducationalActivity;
            $activity->fill($attributes);
            $activity->forceFill([
                'status' => EducationalActivityStatus::PLANNED,
            ]);
            $activity->save();

            return $activity->refresh();
        });
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function update(
        EducationalActivity $activity,
        array $attributes
    ): EducationalActivity {
        return DB::transaction(function () use (
            $activity,
            $attributes
        ): EducationalActivity {
            $locked = $this->lockActivity($activity);
            $centerId = (int) $attributes['educational_center_id'];
            $locationId = $attributes['school_location_id'] ?? null;

            if ($centerId !== (int) $locked->educational_center_id) {
                $this->assertActiveCenter($centerId);
            }

            if (
                $this->normalizedId($locationId)
                !== $this->normalizedId($locked->school_location_id)
            ) {
                $this->assertActiveLocation($locationId);
            }

            if (
                $locked->status === EducationalActivityStatus::COMPLETED
                && (int) ($attributes['expected_students'] ?? 0) < 1
            ) {
                throw ValidationException::withMessages([
                    'expected_students' => self::COMPLETION_STUDENTS_ERROR,
                ]);
            }

            $locked->fill($attributes);
            $locked->save();

            return $locked->refresh();
        });
    }

    public function complete(
        EducationalActivity $activity
    ): EducationalActivity {
        return DB::transaction(function () use ($activity): EducationalActivity {
            $locked = $this->lockActivity($activity);
            $this->assertPlanned($locked);

            if ((int) $locked->expected_students < 1) {
                throw ValidationException::withMessages([
                    'expected_students' => self::COMPLETION_STUDENTS_ERROR,
                ]);
            }

            $locked->forceFill([
                'status' => EducationalActivityStatus::COMPLETED,
            ])->save();

            return $locked->refresh();
        });
    }

    public function cancel(
        EducationalActivity $activity
    ): EducationalActivity {
        return DB::transaction(function () use ($activity): EducationalActivity {
            $locked = $this->lockActivity($activity);
            $this->assertPlanned($locked);
            $locked->forceFill([
                'status' => EducationalActivityStatus::CANCELLED,
            ])->save();

            return $locked->refresh();
        });
    }

    public function delete(EducationalActivity $activity): void
    {
        DB::transaction(function () use ($activity): void {
            $locked = $this->lockActivity($activity);

            if ($locked->status !== EducationalActivityStatus::PLANNED) {
                throw ValidationException::withMessages([
                    'status' => self::DELETE_ERROR,
                ]);
            }

            $locked->delete();
        });
    }

    private function assertActiveCenter(int $centerId): void
    {
        if (
            ! EducationalCenter::query()
                ->whereKey($centerId)
                ->active()
                ->exists()
        ) {
            throw ValidationException::withMessages([
                'educational_center_id' => self::CENTER_ACTIVE_ERROR,
            ]);
        }
    }

    private function assertActiveLocation(mixed $locationId): void
    {
        if ($locationId === null || $locationId === '') {
            return;
        }

        if (
            ! SchoolLocation::query()
                ->whereKey((int) $locationId)
                ->active()
                ->exists()
        ) {
            throw ValidationException::withMessages([
                'school_location_id' => self::LOCATION_ACTIVE_ERROR,
            ]);
        }
    }

    private function assertPlanned(EducationalActivity $activity): void
    {
        if ($activity->status !== EducationalActivityStatus::PLANNED) {
            throw ValidationException::withMessages([
                'status' => self::TRANSITION_ERROR,
            ]);
        }
    }

    private function lockActivity(
        EducationalActivity $activity
    ): EducationalActivity {
        return EducationalActivity::query()
            ->lockForUpdate()
            ->findOrFail($activity->getKey());
    }

    private function normalizedId(mixed $value): ?int
    {
        return $value === null || $value === '' ? null : (int) $value;
    }
}
