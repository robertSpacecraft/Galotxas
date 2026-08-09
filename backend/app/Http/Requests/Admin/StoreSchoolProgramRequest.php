<?php

namespace App\Http\Requests\Admin;

use App\Models\SchoolLocation;
use App\Models\SchoolProgram;
use App\Services\SchoolProgramService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreSchoolProgramRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'name' => trim((string) $this->input('name')),
            'public_description' => $this->nullableTrimmed('public_description'),
            'enrollment_information' => $this->nullableTrimmed('enrollment_information'),
            'contact_phone' => $this->nullableTrimmed('contact_phone'),
            'contact_email' => $this->nullableTrimmed('contact_email'),
            'is_public' => $this->has('is_public') ? $this->boolean('is_public') : null,
            'enrollments_open' => $this->has('enrollments_open')
                ? $this->boolean('enrollments_open')
                : null,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'public_description' => ['nullable', 'string', 'max:5000'],
            'enrollment_information' => ['nullable', 'string', 'max:5000'],
            'is_public' => ['required', 'boolean'],
            'enrollments_open' => ['required', 'boolean'],
            'default_school_location_id' => [
                'nullable',
                'integer',
                'exists:school_locations,id',
            ],
            'contact_phone' => ['nullable', 'string', 'max:50'],
            'contact_email' => ['nullable', 'email', 'max:255'],
            'sort_order' => ['required', 'integer', 'min:0', 'max:65535'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($validator->errors()->hasAny([
                'is_public',
                'default_school_location_id',
            ])) {
                return;
            }

            if ($this->boolean('is_public') && $this->otherPublicProgramExists()) {
                $validator->errors()->add(
                    'is_public',
                    SchoolProgramService::PUBLICATION_ERROR
                );
            }

            if (! $this->boolean('is_public') || ! $this->filled('default_school_location_id')) {
                return;
            }

            $locationIsActive = SchoolLocation::query()
                ->whereKey($this->integer('default_school_location_id'))
                ->active()
                ->exists();

            if (! $locationIsActive) {
                $validator->errors()->add(
                    'default_school_location_id',
                    'La ubicación habitual debe estar activa para publicar el programa.'
                );
            }
        });
    }

    private function otherPublicProgramExists(): bool
    {
        $program = $this->route('program');

        return SchoolProgram::query()
            ->effectivelyPublic()
            ->when(
                $program instanceof SchoolProgram,
                fn ($query) => $query->whereKeyNot($program->getKey())
            )
            ->exists();
    }

    private function nullableTrimmed(string $field): ?string
    {
        $value = trim((string) $this->input($field));

        return $value === '' ? null : $value;
    }
}
