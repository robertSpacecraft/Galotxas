<?php

namespace App\Http\Requests\Admin;

use App\Enums\PublicIdentityAuthorizationMode;
use App\Enums\PublicIdentityAuthorizationState;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ListPublicIdentityAuthorizationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'state' => ['nullable', Rule::in(PublicIdentityAuthorizationState::values())],
            'mode' => ['nullable', Rule::in(PublicIdentityAuthorizationMode::values())],
            'age_group' => ['nullable', Rule::in(['under_14', '14_to_17', 'adult', 'unlinked'])],
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:from'],
        ];
    }
}
