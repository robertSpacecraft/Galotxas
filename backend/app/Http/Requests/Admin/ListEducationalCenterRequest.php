<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ListEducationalCenterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'locality' => $this->filled('locality')
                ? trim((string) $this->input('locality'))
                : null,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'active' => ['nullable', Rule::in(['0', '1', 0, 1])],
            'locality' => ['nullable', 'string', 'max:255'],
        ];
    }
}
