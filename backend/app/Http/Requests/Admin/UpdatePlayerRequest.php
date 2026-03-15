<?php

namespace App\Http\Requests\Admin;

use App\Enums\PlayerGender;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdatePlayerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $playerId = $this->route('player')->id;

        return [
            'user_id' => [
                'required',
                'integer',
                'exists:users,id',
                Rule::unique('players', 'user_id')->ignore($playerId),
            ],
            'dni' => [
                'nullable',
                'string',
                'max:20',
                Rule::unique('players', 'dni')->ignore($playerId),
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
