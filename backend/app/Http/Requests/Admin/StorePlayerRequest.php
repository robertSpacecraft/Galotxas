<?php

namespace App\Http\Requests\Admin;

use App\Enums\PlayerGender;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePlayerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'user_id' => [
                'required',
                'integer',
                'exists:users,id',
                Rule::unique('players', 'user_id'),
            ],
            'dni' => [
                'nullable',
                'string',
                'max:20',
                'unique:players,dni',
            ],
            'birth_date' => [
                'nullable',
                'date',
                'before:today',
            ],
            'gender' => [
                'nullable',
                Rule::in(PlayerGender::values()),
            ],
            'level' => [
                'required',
                'integer',
                'min:1',
                'max:10',
            ],
            'active' => [
                'nullable',
                'boolean',
            ],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'active' => $this->boolean('active'),
            'dni' => $this->filled('dni') ? strtoupper(trim((string) $this->dni)) : null,
        ]);
    }

    public function attributes(): array
    {
        return [
            'user_id' => 'usuario',
            'dni' => 'DNI',
            'birth_date' => 'fecha de nacimiento',
            'gender' => 'género',
            'level' => 'nivel',
            'active' => 'activo',
        ];
    }
}
