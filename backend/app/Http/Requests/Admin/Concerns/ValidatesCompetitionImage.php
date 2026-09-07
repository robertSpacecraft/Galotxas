<?php

namespace App\Http\Requests\Admin\Concerns;

use Illuminate\Validation\Rule;

trait ValidatesCompetitionImage
{
    private function competitionImageRules(): array
    {
        // These Form Requests are shared with the JSON Admin API, which does not manage files.
        if (! $this->routeIs('admin.*')) {
            return [];
        }

        return [
            'image' => [
                'nullable', 'file', 'image', 'mimes:jpeg,png,webp',
                'max:'.(int) config('media.profiles.banner.input_max_kb'),
                Rule::prohibitedIf(! $this->isMethod('post') && $this->boolean('remove_image')),
            ],
            'remove_image' => ['sometimes', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'image.prohibited' => 'No puedes subir una imagen nueva y retirarla en la misma operación.',
            'image.max' => 'La imagen no puede superar :max KB.',
            'image.mimes' => 'La imagen debe ser JPEG, PNG o WebP.',
        ];
    }
}
