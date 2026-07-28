<?php

namespace App\Http\Requests\Admin;

use App\Enums\SchoolDayOfWeek;
use App\Models\SchoolLevel;
use App\Models\SchoolLocation;
use App\Models\SchoolSchedule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;
use Illuminate\Validation\Validator;

class StoreSchoolScheduleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'day_of_week' => $this->filled('day_of_week')
                ? $this->integer('day_of_week')
                : null,
            'is_active' => $this->has('is_active') ? $this->boolean('is_active') : null,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'school_level_id' => ['required', 'integer', 'exists:school_levels,id'],
            'school_location_id' => ['required', 'integer', 'exists:school_locations,id'],
            'day_of_week' => ['required', new Enum(SchoolDayOfWeek::class)],
            'starts_at' => ['required', 'date_format:H:i'],
            'ends_at' => ['required', 'date_format:H:i', 'after:starts_at'],
            'is_active' => ['required', 'boolean'],
            'sort_order' => ['required', 'integer', 'min:0', 'max:65535'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($validator->errors()->hasAny([
                'school_level_id',
                'school_location_id',
                'day_of_week',
                'starts_at',
                'ends_at',
                'is_active',
            ])) {
                return;
            }

            if ($this->boolean('is_active')) {
                $this->validateActiveRelations($validator);
            }

            if ($this->exactDuplicateExists()) {
                $validator->errors()->add(
                    'starts_at',
                    'Ya existe un horario idéntico para ese nivel y ubicación.'
                );
            }
        });
    }

    private function validateActiveRelations(Validator $validator): void
    {
        $levelIsActive = SchoolLevel::query()
            ->whereKey($this->integer('school_level_id'))
            ->where('is_active', true)
            ->exists();

        if (! $levelIsActive) {
            $validator->errors()->add(
                'school_level_id',
                'El nivel debe estar activo para activar el horario.'
            );
        }

        $locationIsActive = SchoolLocation::query()
            ->whereKey($this->integer('school_location_id'))
            ->active()
            ->exists();

        if (! $locationIsActive) {
            $validator->errors()->add(
                'school_location_id',
                'La ubicación debe estar activa para activar el horario.'
            );
        }
    }

    private function exactDuplicateExists(): bool
    {
        $schedule = $this->route('schedule');

        return SchoolSchedule::query()
            ->where('school_level_id', $this->integer('school_level_id'))
            ->where('school_location_id', $this->integer('school_location_id'))
            ->where('day_of_week', $this->integer('day_of_week'))
            ->whereTime('starts_at', $this->input('starts_at'))
            ->whereTime('ends_at', $this->input('ends_at'))
            ->when(
                $schedule instanceof SchoolSchedule,
                fn ($query) => $query->whereKeyNot($schedule->getKey())
            )
            ->exists();
    }
}
