<?php

namespace App\Http\Requests\Admin;

use App\Enums\PublicIdentityAuthorizationMode;
use App\Models\PublicIdentityAuthorization;
use App\Services\PublicIdentityNoticeService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StorePlayerPublicIdentityAuthorizationRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $this->merge([
            'guardian_name' => $this->normalizedText('guardian_name'),
            'guardian_relationship' => $this->normalizedText('guardian_relationship'),
            'guardian_email' => $this->normalizedEmail(),
            'mode' => $this->trimmedText('mode'),
            'notice_id' => $this->trimmedText('notice_id'),
            'notice_version' => $this->trimmedText('notice_version'),
        ]);
    }

    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'guardian_name' => ['required', 'string', 'max:255'],
            'guardian_relationship' => ['required', 'string', 'max:100'],
            'guardian_email' => ['required', 'email:rfc', 'max:254'],
            'mode' => ['required', Rule::in([
                PublicIdentityAuthorizationMode::ALIAS->value,
                PublicIdentityAuthorizationMode::NAME_INITIAL->value,
            ])],
            'guardian_authority_declared' => ['required', 'accepted'],
            'notice_id' => ['required', 'string', 'max:80'],
            'notice_version' => ['required', 'string', 'max:20'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $allowedFields = [
                '_token',
                'guardian_name',
                'guardian_relationship',
                'guardian_email',
                'mode',
                'guardian_authority_declared',
                'notice_id',
                'notice_version',
            ];

            if (array_diff(array_keys($this->all()), $allowedFields) !== []) {
                $validator->errors()->add('payload', 'La solicitud contiene campos no permitidos.');
            }

            if ($validator->errors()->hasAny(['notice_id', 'notice_version'])) {
                return;
            }

            if (! app(PublicIdentityNoticeService::class)->recognizes(
                (string) $this->input('notice_id'),
                (string) $this->input('notice_version'),
                PublicIdentityAuthorization::SCOPE
            )) {
                $validator->errors()->add(
                    'notice_version',
                    'El aviso de autorización no está vigente.'
                );
            }
        });
    }

    private function normalizedText(string $field): mixed
    {
        $value = $this->input($field);

        return is_string($value) ? Str::of($value)->squish()->toString() : $value;
    }

    private function trimmedText(string $field): mixed
    {
        $value = $this->input($field);

        return is_string($value) ? trim($value) : $value;
    }

    private function normalizedEmail(): mixed
    {
        $email = $this->input('guardian_email');

        return is_string($email) ? Str::lower(trim($email)) : $email;
    }
}
