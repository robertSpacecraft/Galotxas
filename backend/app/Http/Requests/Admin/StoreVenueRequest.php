<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreVenueRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'court_number' => [
                'required',
                'integer',
                'min:1',
                'max:4294967295',
                Rule::unique('venues', 'court_number'),
            ],
            'name' => ['required', 'string', 'max:255', Rule::unique('venues', 'name')],
            'location' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
        ];
    }

    public function attributes(): array
    {
        return [
            'court_number' => 'número de pista',
        ];
    }
}
