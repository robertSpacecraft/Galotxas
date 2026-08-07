<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class PlaceContactRetentionHold extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'retention_hold_reason' => ['required', 'string', 'min:3', 'max:500'],
        ];
    }
}
