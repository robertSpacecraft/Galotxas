<?php

namespace App\Http\Requests\Admin;

use App\Enums\ChampionshipType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

class StoreChampionshipRequest extends FormRequest
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
            'registration_status' => ['required', 'in:open,closed'],
            'registration_starts_at' => ['nullable', 'date'],
            'registration_ends_at' => ['nullable', 'date', 'after_or_equal:registration_starts_at'],
        ];
    }
}
