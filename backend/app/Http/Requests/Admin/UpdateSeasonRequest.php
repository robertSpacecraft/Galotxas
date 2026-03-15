<?php

namespace App\Http\Requests\Admin;

use App\Enums\SeasonStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

class UpdateSeasonRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'status' => ['required', new Enum(SeasonStatus::class)],
        ];
    }
}
