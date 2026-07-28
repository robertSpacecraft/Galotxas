<?php

namespace App\Http\Requests\Admin;

use App\Models\SchoolEnrollment;
use App\Models\SchoolLevel;
use App\Services\SchoolEnrollmentService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

abstract class SchoolEnrollmentLevelRequest extends FormRequest
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
            'school_level_id' => ['required', 'integer', 'exists:school_levels,id'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($validator->errors()->has('school_level_id')) {
                return;
            }

            /** @var SchoolEnrollment $enrollment */
            $enrollment = $this->route('enrollment');

            $validLevel = SchoolLevel::query()
                ->whereKey($this->integer('school_level_id'))
                ->where('school_program_id', $enrollment->school_program_id)
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
}
