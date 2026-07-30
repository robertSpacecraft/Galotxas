<?php

namespace App\Http\Requests\Admin;

use App\Enums\EducationalActivityStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ListEducationalActivityRequest extends FormRequest
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
            'center' => [
                'nullable',
                'integer',
                'exists:educational_centers,id',
            ],
            'status' => [
                'nullable',
                'string',
                Rule::enum(EducationalActivityStatus::class),
            ],
            'date_from' => ['nullable', 'date_format:Y-m-d'],
            'date_to' => [
                'nullable',
                'date_format:Y-m-d',
                'after_or_equal:date_from',
            ],
        ];
    }
}
