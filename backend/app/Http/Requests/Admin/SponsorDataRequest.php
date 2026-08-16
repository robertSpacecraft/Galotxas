<?php

namespace App\Http\Requests\Admin;

use App\Rules\HttpsExternalUrl;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

abstract class SponsorDataRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'name' => trim((string) $this->input('name')),
            'website_url' => $this->nullableTrimmed('website_url'),
            'is_active' => $this->has('is_active') ? $this->boolean('is_active') : null,
            'starts_at' => $this->nullableTrimmed('starts_at'),
            'ends_at' => $this->nullableTrimmed('ends_at'),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'logo' => [
                Rule::requiredIf($this->isMethod('post')),
                'nullable',
                'file',
                'mimetypes:image/jpeg,image/png,image/webp',
                'max:8192',
            ],
            'website_url' => ['nullable', 'string', 'max:2048', new HttpsExternalUrl],
            'sort_order' => ['required', 'integer', 'min:0', 'max:65535'],
            'is_active' => ['required', 'boolean'],
            'starts_at' => ['nullable', 'date_format:Y-m-d\TH:i'],
            'ends_at' => [
                'nullable',
                'date_format:Y-m-d\TH:i',
                Rule::when($this->filled('starts_at'), 'after:starts_at'),
            ],
        ];
    }

    private function nullableTrimmed(string $field): ?string
    {
        $value = trim((string) $this->input($field));

        return $value === '' ? null : $value;
    }
}
