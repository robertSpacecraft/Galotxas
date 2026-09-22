<?php

namespace App\Http\Requests\Api;

use App\Services\AccountProfileNoticeService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\Validator;

class RegisterUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'lastname' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'confirmed', Password::min(8)],
            'profile_declaration_accepted' => ['required', 'accepted'],
            'profile_notice_id' => ['required', 'string', 'max:80'],
            'profile_notice_version' => ['required', 'string', 'max:20'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($validator->errors()->hasAny(['profile_notice_id', 'profile_notice_version'])) {
                return;
            }

            if (! app(AccountProfileNoticeService::class)->recognizes(
                (string) $this->input('profile_notice_id'),
                (string) $this->input('profile_notice_version')
            )) {
                $validator->errors()->add(
                    'profile_notice_version',
                    'La versión del aviso de cuenta y perfil no está vigente.'
                );
            }
        });
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'email' => strtolower(trim((string) $this->email)),
        ]);
    }

    public function attributes(): array
    {
        return [
            'name' => 'nombre',
            'lastname' => 'apellidos',
            'email' => 'correo electrónico',
            'password' => 'contraseña',
            'password_confirmation' => 'confirmación de contraseña',
            'profile_declaration_accepted' => 'declaración de exactitud',
            'profile_notice_id' => 'aviso de cuenta y perfil',
            'profile_notice_version' => 'versión del aviso de cuenta y perfil',
        ];
    }
}
