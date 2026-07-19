<?php

namespace App\Http\Requests\Admin;

use App\Enums\CategoryGender;
use App\Enums\CategoryStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

class UpdateCategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'level' => ['nullable', 'integer', 'min:1', 'max:10'],
            'gender' => ['required', new Enum(CategoryGender::class)],
            'status' => ['required', new Enum(CategoryStatus::class)],
        ];
    }
}
