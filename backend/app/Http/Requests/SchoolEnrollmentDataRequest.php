<?php

namespace App\Http\Requests;

use App\Services\SchoolEnrollmentAgeService;
use Carbon\CarbonImmutable;
use DateTimeImmutable;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use InvalidArgumentException;

abstract class SchoolEnrollmentDataRequest extends FormRequest
{
    protected ?bool $participantIsMinor = null;

    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $birthDate = trim((string) $this->input('participant_birth_date'));
        $parsedBirthDate = $this->parseDate($birthDate);

        if ($parsedBirthDate !== null) {
            try {
                $this->participantIsMinor = SchoolEnrollmentAgeService::isMinor(
                    $parsedBirthDate,
                    $this->referenceDate()
                );
            } catch (InvalidArgumentException) {
                $this->participantIsMinor = null;
            }
        }

        $guardianName = $this->nullableString('guardian_name');
        $guardianRelationship = $this->nullableString('guardian_relationship');

        if ($this->participantIsMinor === false) {
            $guardianName = null;
            $guardianRelationship = null;
        }

        $this->merge([
            'participant_name' => trim((string) $this->input('participant_name')),
            'participant_birth_date' => $birthDate,
            'contact_phone' => trim((string) $this->input('contact_phone')),
            'contact_email' => strtolower(trim((string) $this->input('contact_email'))),
            'guardian_name' => $guardianName,
            'guardian_relationship' => $guardianRelationship,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    protected function participantRules(): array
    {
        return [
            'participant_name' => ['required', 'string', 'max:255'],
            'participant_birth_date' => [
                'required',
                'date_format:Y-m-d',
                'before_or_equal:today',
            ],
            'contact_phone' => ['required', 'string', 'max:50'],
            'contact_email' => ['required', 'string', 'email', 'max:255'],
            'guardian_name' => [
                Rule::requiredIf(fn (): bool => $this->participantIsMinor === true),
                'nullable',
                'string',
                'max:255',
            ],
            'guardian_relationship' => [
                Rule::requiredIf(fn (): bool => $this->participantIsMinor === true),
                'nullable',
                'string',
                'max:100',
            ],
        ];
    }

    public function participantWasMinor(): ?bool
    {
        return $this->participantIsMinor;
    }

    abstract protected function referenceDate(): CarbonImmutable;

    private function nullableString(string $field): ?string
    {
        $value = trim((string) $this->input($field));

        return $value === '' ? null : $value;
    }

    private function parseDate(string $value): ?CarbonImmutable
    {
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);
        $errors = DateTimeImmutable::getLastErrors();

        if (
            $date === false
            || (
                $errors !== false
                && ($errors['warning_count'] > 0 || $errors['error_count'] > 0)
            )
        ) {
            return null;
        }

        return CarbonImmutable::instance($date);
    }
}
