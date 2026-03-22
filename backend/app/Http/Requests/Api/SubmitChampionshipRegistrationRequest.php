<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class SubmitChampionshipRegistrationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'suggested_category_id' => ['nullable', 'integer', 'exists:categories,id'],
            'comment' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
