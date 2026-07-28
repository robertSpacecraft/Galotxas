<?php

namespace App\Http\Requests\Admin;

use App\Enums\SchoolEnrollmentStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ListSchoolEnrollmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'program' => ['nullable', 'integer', 'exists:school_programs,id'],
            'level' => ['nullable', 'integer', 'exists:school_levels,id'],
            'status' => ['nullable', 'string', Rule::enum(SchoolEnrollmentStatus::class)],
        ];
    }
}
