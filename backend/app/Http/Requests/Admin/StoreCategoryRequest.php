<?php

namespace App\Http\Requests\Admin;

use App\Enums\CategoryGender;
use App\Enums\CategoryStatus;
use App\Models\Category;
use App\Models\Championship;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;
use Illuminate\Validation\Validator;

class StoreCategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'level' => ['nullable', 'integer', 'min:1', 'max:10'],
            'gender' => ['required', new Enum(CategoryGender::class)],
            'status' => ['required', new Enum(CategoryStatus::class)],
            'is_public' => ['required', 'boolean'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if (! $this->boolean('is_public')) {
                return;
            }

            $championship = $this->visibilityChampionship();

            if ($championship && (! $championship->is_public || ! $championship->season?->is_public)) {
                $validator->errors()->add(
                    'is_public',
                    'No puedes hacer pública la categoría mientras su campeonato o temporada sean privados.'
                );
            }
        });
    }

    private function visibilityChampionship(): ?Championship
    {
        $championship = $this->route('championship');

        if ($championship instanceof Championship) {
            return $championship->loadMissing('season');
        }

        $category = $this->route('category');

        if ($category instanceof Category) {
            return $category->loadMissing('championship.season')->championship;
        }

        return null;
    }
}
