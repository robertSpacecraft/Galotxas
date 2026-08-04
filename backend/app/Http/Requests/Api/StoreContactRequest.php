<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Validator;

class StoreContactRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $message = str_replace(
            ["\r\n", "\r"],
            "\n",
            (string) $this->input('message')
        );

        $this->merge([
            'name' => trim((string) $this->input('name')),
            'email' => Str::lower(trim((string) $this->input('email'))),
            'subject' => trim((string) $this->input('subject')),
            'message' => trim($message),
            'website' => trim((string) $this->input('website')),
        ]);
    }

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'min:2', 'max:120'],
            'email' => ['required', 'string', 'email:rfc', 'max:254'],
            'subject' => ['required', 'string', 'min:3', 'max:200'],
            'message' => ['required', 'string', 'min:10', 'max:5000'],
            'privacy_accepted' => ['required', 'accepted'],
            'website' => ['nullable', 'string', 'max:255'],
            'id' => ['prohibited'],
            'status' => ['prohibited'],
            'consent_at' => ['prohibited'],
            'ip_hash' => ['prohibited'],
            'created_at' => ['prohibited'],
            'updated_at' => ['prohibited'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $allowedFields = [
                'name',
                'email',
                'subject',
                'message',
                'privacy_accepted',
                'website',
            ];
            $unexpectedFields = array_diff(
                array_keys($this->all()),
                $allowedFields
            );

            if ($unexpectedFields !== []) {
                $validator->errors()->add(
                    'payload',
                    'La solicitud contiene campos no permitidos.'
                );
            }
        });
    }

    public function honeypotIsFilled(): bool
    {
        return $this->validated('website') !== null
            && $this->validated('website') !== '';
    }
}
