<?php

namespace App\Http\Requests\Admin;

use App\Enums\EducationalActivityStatus;
use App\Models\EducationalActivity;
use App\Models\EducationalCenter;
use App\Models\SchoolLocation;
use App\Services\EducationalActivityService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

abstract class EducationalActivityDataRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'educational_center_id' => $this->filled('educational_center_id')
                ? $this->integer('educational_center_id')
                : null,
            'school_location_id' => $this->filled('school_location_id')
                ? $this->integer('school_location_id')
                : null,
            'name' => trim((string) $this->input('name')),
            'activity_date' => trim((string) $this->input('activity_date')),
            'starts_at' => $this->nullableTrimmed('starts_at'),
            'ends_at' => $this->nullableTrimmed('ends_at'),
            'expected_students' => $this->filled('expected_students')
                ? $this->integer('expected_students')
                : null,
            'admin_notes' => $this->nullableTrimmed('admin_notes'),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'educational_center_id' => [
                'required',
                'integer',
                'exists:educational_centers,id',
            ],
            'school_location_id' => [
                'nullable',
                'integer',
                'exists:school_locations,id',
            ],
            'name' => ['required', 'string', 'max:255'],
            'activity_date' => ['required', 'date_format:Y-m-d'],
            'starts_at' => [
                'nullable',
                'required_with:ends_at',
                'date_format:H:i',
            ],
            'ends_at' => [
                'nullable',
                'required_with:starts_at',
                'date_format:H:i',
                'after:starts_at',
            ],
            'expected_students' => [
                'nullable',
                'integer',
                'min:1',
                'max:65535',
            ],
            'admin_notes' => ['nullable', 'string', 'max:5000'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if (
                ! $validator->errors()->has('educational_center_id')
                && $this->requiresActiveCenter()
                && ! EducationalCenter::query()
                    ->whereKey($this->integer('educational_center_id'))
                    ->active()
                    ->exists()
            ) {
                $validator->errors()->add(
                    'educational_center_id',
                    EducationalActivityService::CENTER_ACTIVE_ERROR
                );
            }

            if (
                ! $validator->errors()->has('school_location_id')
                && $this->filled('school_location_id')
                && $this->requiresActiveLocation()
                && ! SchoolLocation::query()
                    ->whereKey($this->integer('school_location_id'))
                    ->active()
                    ->exists()
            ) {
                $validator->errors()->add(
                    'school_location_id',
                    EducationalActivityService::LOCATION_ACTIVE_ERROR
                );
            }

            $activity = $this->activity();

            if (
                $activity?->status === EducationalActivityStatus::COMPLETED
                && (int) $this->input('expected_students') < 1
            ) {
                $validator->errors()->add(
                    'expected_students',
                    EducationalActivityService::COMPLETION_STUDENTS_ERROR
                );
            }
        });
    }

    private function requiresActiveCenter(): bool
    {
        $activity = $this->activity();

        return $activity === null
            || (int) $activity->educational_center_id
                !== $this->integer('educational_center_id');
    }

    private function requiresActiveLocation(): bool
    {
        $activity = $this->activity();

        return $activity === null
            || (int) $activity->school_location_id
                !== $this->integer('school_location_id');
    }

    private function activity(): ?EducationalActivity
    {
        $activity = $this->route('educational_activity');

        return $activity instanceof EducationalActivity ? $activity : null;
    }

    private function nullableTrimmed(string $field): ?string
    {
        $value = trim((string) $this->input($field));

        return $value === '' ? null : $value;
    }
}
