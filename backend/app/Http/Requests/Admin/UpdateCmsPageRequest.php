<?php

namespace App\Http\Requests\Admin;

use App\Enums\CmsPageStatus;
use App\Models\CmsPage;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;
use Illuminate\Validation\Validator;

class UpdateCmsPageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $cmsPage = $this->route('cmsPage');

        return [
            'title' => ['required', 'string', 'max:255'],
            'slug' => [
                'required',
                'string',
                'max:255',
                'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/',
                Rule::unique('cms_pages', 'slug')->ignore($cmsPage?->id),
            ],
            'status' => ['required', new Enum(CmsPageStatus::class)],
            'published_at' => ['nullable', 'date'],
            'seo_title' => ['nullable', 'string', 'max:255'],
            'seo_description' => ['nullable', 'string', 'max:500'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            if ($validator->errors()->has('status')
                || $this->input('status') !== CmsPageStatus::PUBLISHED->value) {
                return;
            }

            $cmsPage = $this->route('cmsPage');

            if ($cmsPage instanceof CmsPage && ! $cmsPage->hasPublishableContent()) {
                $validator->errors()->add(
                    'status',
                    'No se puede publicar una página sin bloques. Añade al menos un bloque válido antes de publicarla.'
                );
            }
        });
    }
}
