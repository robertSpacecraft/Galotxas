<?php

namespace App\Http\Requests\Admin;

use App\Enums\ChampionshipType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

class UpdateChampionshipRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', new Enum(ChampionshipType::class)],
        ];
    }
}
