<?php

namespace App\Http\Requests\Admin;

use App\Enums\ChampionshipRegistrationStatus;
use App\Enums\ChampionshipStatus;
use App\Enums\ChampionshipType;
use App\Models\Season;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;
use Illuminate\Validation\Validator;

class StoreChampionshipRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'season_id' => ['required', 'integer', 'exists:seasons,id'],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'type' => ['required', new Enum(ChampionshipType::class)],
            'status' => ['required', new Enum(ChampionshipStatus::class)],
            'is_public' => ['required', 'boolean'],
            'start_date' => ['nullable', 'date'],
            'end_date' => [
                'nullable',
                'date',
                Rule::when($this->filled('start_date'), ['after_or_equal:start_date']),
            ],
            'registration_status' => ['required', new Enum(ChampionshipRegistrationStatus::class)],
            'registration_starts_at' => ['nullable', 'date'],
            'registration_ends_at' => [
                'nullable',
                'date',
                Rule::when(
                    $this->filled('registration_starts_at'),
                    ['after_or_equal:registration_starts_at']
                ),
            ],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if (! $this->boolean('is_public') || $validator->errors()->has('season_id')) {
                return;
            }

            $season = Season::query()->find($this->input('season_id'));

            if ($season && ! $season->is_public) {
                $validator->errors()->add(
                    'is_public',
                    'No puedes hacer público el campeonato mientras su temporada sea privada.'
                );
            }
        });
    }
}
