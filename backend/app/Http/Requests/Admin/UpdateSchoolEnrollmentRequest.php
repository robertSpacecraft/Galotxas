<?php

namespace App\Http\Requests\Admin;

use App\Http\Requests\SchoolEnrollmentDataRequest;
use App\Models\SchoolEnrollment;
use Carbon\CarbonImmutable;

class UpdateSchoolEnrollmentRequest extends SchoolEnrollmentDataRequest
{
    protected function prepareForValidation(): void
    {
        parent::prepareForValidation();

        $this->merge([
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
            'admin_notes' => ['nullable', 'string', 'max:10000'],
        ];
    }

    protected function referenceDate(): CarbonImmutable
    {
        /** @var SchoolEnrollment $enrollment */
        $enrollment = $this->route('enrollment');

        return CarbonImmutable::instance($enrollment->requested_at);
    }

    private function nullableString(string $field): ?string
    {
        $value = trim((string) $this->input($field));

        return $value === '' ? null : $value;
    }
}
