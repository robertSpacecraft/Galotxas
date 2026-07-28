<?php

namespace App\Http\Requests\Admin;

use App\Models\SchoolProgram;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreSchoolLevelRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'name' => trim((string) $this->input('name')),
            'minimum_age' => $this->nullableValue('minimum_age'),
            'maximum_age' => $this->nullableValue('maximum_age'),
            'is_active' => $this->has('is_active') ? $this->boolean('is_active') : null,
            'is_public' => $this->has('is_public') ? $this->boolean('is_public') : null,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'school_program_id' => ['required', 'integer', 'exists:school_programs,id'],
            'name' => ['required', 'string', 'max:255'],
            'minimum_age' => [
                'nullable',
                'integer',
                'min:0',
                'max:255',
                Rule::when($this->filled('maximum_age'), ['lte:maximum_age']),
            ],
            'maximum_age' => ['nullable', 'integer', 'min:0', 'max:255'],
            'is_active' => ['required', 'boolean'],
            'is_public' => ['required', 'boolean'],
            'sort_order' => ['required', 'integer', 'min:0', 'max:65535'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if (
                ! $this->boolean('is_public')
                || $validator->errors()->has('school_program_id')
            ) {
                return;
            }

            $programIsPublic = SchoolProgram::query()
                ->whereKey($this->integer('school_program_id'))
                ->effectivelyPublic()
                ->exists();

            if (! $programIsPublic) {
                $validator->errors()->add(
                    'is_public',
                    'No puedes hacer público el nivel mientras su programa sea privado.'
                );
            }
        });
    }

    private function nullableValue(string $field): mixed
    {
        $value = $this->input($field);

        return $value === '' ? null : $value;
    }
}
