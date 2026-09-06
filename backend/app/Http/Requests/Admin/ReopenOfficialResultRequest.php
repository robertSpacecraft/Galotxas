<?php

namespace App\Http\Requests\Admin;

use App\Enums\UserRole;
use Illuminate\Foundation\Http\FormRequest;

class ReopenOfficialResultRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user !== null
            && $user->role === UserRole::ADMIN->value
            && $user->active;
    }

    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return [
            'reason' => ['required', 'string', 'max:2000'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'reason.required' => 'Debes indicar el motivo de la reapertura.',
            'reason.string' => 'El motivo de la reapertura debe ser texto.',
            'reason.max' => 'El motivo de la reapertura no puede superar los 2000 caracteres.',
        ];
    }
}
