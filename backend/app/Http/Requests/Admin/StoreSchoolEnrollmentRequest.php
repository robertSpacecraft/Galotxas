<?php

namespace App\Http\Requests\Admin;

use App\Http\Requests\SchoolEnrollmentDataRequest;
use App\Models\SchoolLevel;
use App\Services\SchoolEnrollmentService;
use Carbon\CarbonImmutable;
use Illuminate\Validation\Validator;

class StoreSchoolEnrollmentRequest extends SchoolEnrollmentDataRequest
{
    private CarbonImmutable $requestDate;

    protected function prepareForValidation(): void
    {
        $this->requestDate = CarbonImmutable::now();

        parent::prepareForValidation();

        $this->merge([
            'school_level_id' => $this->input('school_level_id') === ''
                ? null
                : $this->input('school_level_id'),
            'admin_notes' => $this->nullableString('admin_notes'),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            ...$this->participantRules(),
            'school_program_id' => ['required', 'integer', 'exists:school_programs,id'],
            'school_level_id' => ['nullable', 'integer', 'exists:school_levels,id'],
            'admin_notes' => ['nullable', 'string', 'max:10000'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if (
                $validator->errors()->has('school_program_id')
                || $validator->errors()->has('school_level_id')
                || ! $this->filled('school_level_id')
            ) {
                return;
            }

            $validLevel = SchoolLevel::query()
                ->whereKey($this->integer('school_level_id'))
                ->where('school_program_id', $this->integer('school_program_id'))
                ->where('is_active', true)
                ->exists();

            if (! $validLevel) {
                $validator->errors()->add(
                    'school_level_id',
                    SchoolEnrollmentService::ADMIN_LEVEL_ERROR
                );
            }
        });
    }

    protected function referenceDate(): CarbonImmutable
    {
        return $this->requestDate;
    }

    private function nullableString(string $field): ?string
    {
        $value = trim((string) $this->input($field));

        return $value === '' ? null : $value;
    }
}
