<?php

namespace App\Http\Requests\Admin;

use App\Enums\ContactNotificationStatus;
use App\Enums\ContactRequestStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

class ListContactRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $this->merge([
            'status' => $this->input('status') === ''
                ? null
                : $this->input('status'),
            'notification_status' => $this->input('notification_status') === ''
                ? null
                : $this->input('notification_status'),
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
            'status' => ['nullable', new Enum(ContactRequestStatus::class)],
            'notification_status' => ['nullable', new Enum(ContactNotificationStatus::class)],
        ];
    }
}
