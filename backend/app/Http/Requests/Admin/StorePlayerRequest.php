<?php

namespace App\Http\Requests\Admin;

use App\Enums\PlayerGender;
use App\Rules\AdultRequiresDni;
use App\Services\PlayerProfileNormalizer;
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
            'nickname' => [
                'nullable',
                'string',
                'max:255',
                Rule::unique('players', 'nickname'),
            ],
            'dni' => [
                'nullable',
                'string',
                'max:20',
                'unique:players,dni',
                new AdultRequiresDni,
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
            'license_number' => [
                'nullable',
                'string',
                'max:50',
                'unique:players,license_number',
            ],
            'dominant_hand' => [
                'nullable',
                Rule::in(['right', 'left', 'both']),
            ],
            'notes' => [
                'nullable',
                'string',
            ],
            'active' => [
                'nullable',
                'boolean',
            ],
        ];
    }

    protected function prepareForValidation(): void
    {
        $normalizer = app(PlayerProfileNormalizer::class);

        $this->merge([
            'active' => $this->boolean('active'),
            'nickname' => $normalizer->nickname($this->input('nickname')),
            'dni' => $this->filled('dni') ? strtoupper(trim((string) $this->dni)) : null,
            'license_number' => $normalizer->licenseNumber($this->input('license_number')),
            'dominant_hand' => $this->filled('dominant_hand') ? trim((string) $this->dominant_hand) : null,
            'notes' => $normalizer->optionalText($this->input('notes')),
        ]);
    }

    public function attributes(): array
    {
        return [
            'user_id' => 'usuario',
            'nickname' => 'apodo',
            'dni' => 'DNI',
            'birth_date' => 'fecha de nacimiento',
            'gender' => 'género',
            'level' => 'nivel',
            'license_number' => 'número de licencia',
            'dominant_hand' => 'mano dominante',
            'notes' => 'notas',
            'active' => 'activo',
        ];
    }
}
