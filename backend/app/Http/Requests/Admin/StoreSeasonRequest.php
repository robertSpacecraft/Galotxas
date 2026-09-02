<?php

namespace App\Http\Requests\Admin;

use App\Enums\SeasonStatus;
use App\Models\Season;
use App\Services\SeasonService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;
use Illuminate\Validation\Validator;

class StoreSeasonRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'status' => ['required', new Enum(SeasonStatus::class)],
            'is_public' => ['required', 'boolean'],
            'start_date' => ['nullable', 'date'],
            'end_date' => [
                'nullable',
                'date',
                Rule::when($this->filled('start_date'), ['after_or_equal:start_date']),
            ],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if (
                $validator->errors()->has('status')
                || $this->input('status') !== SeasonStatus::ACTIVE->value
            ) {
                return;
            }

            $season = $this->route('season');
            $anotherActiveSeasonExists = Season::query()
                ->where('status', SeasonStatus::ACTIVE->value)
                ->when(
                    $season instanceof Season,
                    fn ($query) => $query->whereKeyNot($season->getKey())
                )
                ->exists();

            if ($anotherActiveSeasonExists) {
                $validator->errors()->add('status', SeasonService::ACTIVE_CONFLICT_ERROR);
            }
        });
    }
}
