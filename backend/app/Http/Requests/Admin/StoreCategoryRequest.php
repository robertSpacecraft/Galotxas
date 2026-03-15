<?php

namespace App\Http\Requests\Admin;

use App\Enums\CategoryGender;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

class StoreCategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'level' => ['required', 'integer', 'min:1', 'max:10'],
            'gender' => ['required', new Enum(CategoryGender::class)],
        ];
    }
}
