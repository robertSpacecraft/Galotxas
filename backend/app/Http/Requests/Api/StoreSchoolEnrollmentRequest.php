<?php

namespace App\Http\Requests\Api;

use App\Http\Requests\SchoolEnrollmentDataRequest;
use App\Models\SchoolLevel;
use App\Models\SchoolProgram;
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
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            ...$this->participantRules(),
            'school_level_id' => ['nullable', 'integer'],
            'school_program_id' => ['prohibited'],
            'user_id' => ['prohibited'],
            'status' => ['prohibited'],
            'requested_at' => ['prohibited'],
            'activated_at' => ['prohibited'],
            'rejected_at' => ['prohibited'],
            'withdrawn_at' => ['prohibited'],
            'admin_notes' => ['prohibited'],
            'is_public' => ['prohibited'],
            'enrollments_open' => ['prohibited'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $allowedFields = [
                'participant_name',
                'participant_birth_date',
                'contact_phone',
                'contact_email',
                'guardian_name',
                'guardian_relationship',
                'school_level_id',
            ];
            $unexpectedFields = array_diff(array_keys($this->all()), $allowedFields);

            if ($unexpectedFields !== []) {
                $validator->errors()->add(
                    'payload',
                    'La solicitud contiene campos no permitidos.'
                );

                return;
            }

            if (
                $validator->errors()->has('school_level_id')
                || ! $this->filled('school_level_id')
            ) {
                return;
            }

            $program = SchoolProgram::query()
                ->effectivelyPublic()
                ->where('enrollments_open', true)
                ->first();

            if ($program === null) {
                return;
            }

            $levelIsAvailable = SchoolLevel::query()
                ->whereKey($this->integer('school_level_id'))
                ->where('school_program_id', $program->id)
                ->effectivelyPublic()
                ->exists();

            if (! $levelIsAvailable) {
                $validator->errors()->add(
                    'school_level_id',
                    SchoolEnrollmentService::PUBLIC_LEVEL_ERROR
                );
            }
        });
    }

    protected function referenceDate(): CarbonImmutable
    {
        return $this->requestDate;
    }
}
