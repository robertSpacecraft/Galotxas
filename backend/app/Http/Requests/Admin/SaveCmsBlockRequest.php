<?php

namespace App\Http\Requests\Admin;

use App\Enums\CmsBlockType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;
use Illuminate\Validation\Rules\Enum;

class SaveCmsBlockRequest extends FormRequest
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
        return [
            'type' => ['required', new Enum(CmsBlockType::class)],
            'sort_order' => ['required', 'integer', 'min:0', 'max:65535'],
            'text' => ['nullable', 'string', 'max:5000'],
            'level' => ['nullable', 'integer', 'between:1,6'],
            'items_text' => ['nullable', 'string', 'max:5000'],
            'url' => ['nullable', 'string', 'max:2048'],
            'alt' => ['nullable', 'string', 'max:255'],
            'gallery_urls_text' => ['nullable', 'string', 'max:10000'],
            'label' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $type = $this->input('type');

            match ($type) {
                CmsBlockType::HEADING->value => $this->validateHeading($validator),
                CmsBlockType::TEXT->value => $this->validateText($validator),
                CmsBlockType::LIST->value => $this->validateList($validator),
                CmsBlockType::IMAGE->value => $this->validateImage($validator),
                CmsBlockType::GALLERY->value => $this->validateGallery($validator),
                CmsBlockType::BUTTON->value,
                CmsBlockType::DOCUMENT_LINK->value => $this->validateLinkBlock($validator),
                default => null,
            };
        });
    }

    /**
     * @return array<string, mixed>
     */
    public function blockData(): array
    {
        return match ($this->input('type')) {
            CmsBlockType::HEADING->value => [
                'text' => trim((string) $this->input('text')),
                'level' => (int) ($this->input('level') ?: 2),
            ],
            CmsBlockType::TEXT->value => [
                'text' => trim((string) $this->input('text')),
            ],
            CmsBlockType::LIST->value => [
                'items' => $this->linesFrom('items_text'),
            ],
            CmsBlockType::IMAGE->value => [
                'url' => trim((string) $this->input('url')),
                'alt' => $this->nullableTrimmed('alt'),
            ],
            CmsBlockType::GALLERY->value => [
                'urls' => $this->linesFrom('gallery_urls_text'),
            ],
            CmsBlockType::BUTTON->value,
            CmsBlockType::DOCUMENT_LINK->value => [
                'label' => trim((string) $this->input('label')),
                'url' => trim((string) $this->input('url')),
            ],
            default => [],
        };
    }

    private function validateHeading(Validator $validator): void
    {
        $this->requireFilled($validator, 'text', 'El texto del encabezado es obligatorio.');
    }

    private function validateText(Validator $validator): void
    {
        $this->requireFilled($validator, 'text', 'El texto del bloque es obligatorio.');
    }

    private function validateList(Validator $validator): void
    {
        if (empty($this->linesFrom('items_text'))) {
            $validator->errors()->add('items_text', 'La lista debe contener al menos un elemento.');
        }
    }

    private function validateImage(Validator $validator): void
    {
        $this->requireFilled($validator, 'url', 'La URL de la imagen es obligatoria.');
        $this->validateUrlPath($validator, 'url');
    }

    private function validateGallery(Validator $validator): void
    {
        $urls = $this->linesFrom('gallery_urls_text');

        if (empty($urls)) {
            $validator->errors()->add('gallery_urls_text', 'La galería debe contener al menos una URL.');
        }

        foreach ($urls as $url) {
            if (!$this->isUrlOrPath($url)) {
                $validator->errors()->add('gallery_urls_text', 'Cada URL de galería debe ser una URL http(s) o una ruta interna.');
                break;
            }
        }
    }

    private function validateLinkBlock(Validator $validator): void
    {
        $this->requireFilled($validator, 'label', 'La etiqueta es obligatoria.');
        $this->requireFilled($validator, 'url', 'La URL es obligatoria.');
        $this->validateUrlPath($validator, 'url');
    }

    private function requireFilled(Validator $validator, string $field, string $message): void
    {
        if (trim((string) $this->input($field)) === '') {
            $validator->errors()->add($field, $message);
        }
    }

    private function validateUrlPath(Validator $validator, string $field): void
    {
        $value = trim((string) $this->input($field));

        if ($value !== '' && !$this->isUrlOrPath($value)) {
            $validator->errors()->add($field, 'Debe ser una URL http(s) o una ruta interna.');
        }
    }

    private function isUrlOrPath(string $value): bool
    {
        return (str_starts_with($value, '/') && !str_starts_with($value, '//'))
            || str_starts_with($value, 'https://')
            || str_starts_with($value, 'http://');
    }

    /**
     * @return array<int, string>
     */
    private function linesFrom(string $field): array
    {
        return collect(preg_split('/\R/u', (string) $this->input($field)))
            ->map(fn (string $line) => trim($line))
            ->filter()
            ->values()
            ->all();
    }

    private function nullableTrimmed(string $field): ?string
    {
        $value = trim((string) $this->input($field));

        return $value === '' ? null : $value;
    }
}
