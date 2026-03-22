<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateMyPlayerProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $playerId = $this->user()?->player?->id;

        return [
            'nickname' => [
                'nullable',
                'string',
                'max:255',
                Rule::unique('players', 'nickname')->ignore($playerId),
            ],
            'dominant_hand' => [
                'nullable',
                'in:right,left,both',
            ],
            'notes' => [
                'nullable',
                'string',
                'max:2000',
            ],
        ];
    }
}
