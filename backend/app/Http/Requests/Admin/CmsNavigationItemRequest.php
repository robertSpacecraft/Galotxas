<?php

namespace App\Http\Requests\Admin;

use App\Enums\CmsNavigationSlot;
use App\Models\CmsNavigationItem;
use App\Models\CmsPage;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

abstract class CmsNavigationItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $label = $this->input('label');

        $this->merge([
            'label' => is_string($label) ? trim($label) : $label,
            'is_active' => $this->boolean('is_active'),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'cms_page_id' => [
                'required',
                'integer',
                Rule::exists('cms_pages', 'id'),
                Rule::unique('cms_navigation_items', 'cms_page_id')
                    ->where('slot', CmsNavigationSlot::CLUB->value)
                    ->ignore($this->navigationItem()?->id),
            ],
            'slot' => ['prohibited'],
            'label' => ['required', 'string', 'max:80'],
            'sort_order' => ['required', 'integer', 'min:0'],
            'is_active' => ['required', 'boolean'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if (! $validator->errors()->has('label')
                && ! CmsNavigationItem::isValidLabel($this->string('label')->toString())) {
                $validator->errors()->add(
                    'label',
                    'La etiqueta debe ser texto simple, sin HTML, controles ni URL.'
                );
            }

            if ($validator->errors()->has('cms_page_id')) {
                return;
            }

            $page = CmsPage::query()->find($this->integer('cms_page_id'));

            if ($page !== null && CmsNavigationItem::isReservedPageSlug($page->slug)) {
                $validator->errors()->add(
                    'cms_page_id',
                    'La página seleccionada ya dispone de un destino estructural protegido en Club.'
                );
            }
        });
    }

    private function navigationItem(): ?CmsNavigationItem
    {
        $item = $this->route('cmsNavigationItem');

        return $item instanceof CmsNavigationItem ? $item : null;
    }
}
