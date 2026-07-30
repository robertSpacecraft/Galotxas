<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class StoreEducationalCenterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'name' => trim((string) $this->input('name')),
            'locality' => trim((string) $this->input('locality')),
            'contact_name' => $this->nullableTrimmed('contact_name'),
            'contact_phone' => $this->nullableTrimmed('contact_phone'),
            'contact_email' => $this->normalizedEmail(),
            'is_active' => $this->has('is_active')
                ? $this->boolean('is_active')
                : null,
            'admin_notes' => $this->nullableTrimmed('admin_notes'),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'locality' => ['required', 'string', 'max:255'],
            'contact_name' => ['nullable', 'string', 'max:255'],
            'contact_phone' => ['nullable', 'string', 'max:255'],
            'contact_email' => ['nullable', 'email', 'max:255'],
            'is_active' => ['required', 'boolean'],
            'admin_notes' => ['nullable', 'string', 'max:5000'],
        ];
    }

    private function nullableTrimmed(string $field): ?string
    {
        $value = trim((string) $this->input($field));

        return $value === '' ? null : $value;
    }

    private function normalizedEmail(): ?string
    {
        $email = $this->nullableTrimmed('contact_email');

        return $email === null ? null : strtolower($email);
    }
}
