<?php

namespace App\Http\Requests\Admin;

use App\Enums\NewsArticleStatus;
use App\Models\NewsArticle;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;
use Illuminate\Validation\Validator;

abstract class NewsArticleDataRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'title' => trim((string) $this->input('title')),
            'slug' => $this->nullableTrimmed('slug'),
            'excerpt' => trim((string) $this->input('excerpt')),
            'body' => trim((string) $this->input('body')),
            'image_alt' => $this->nullableTrimmed('image_alt'),
            'image_credit' => $this->nullableTrimmed('image_credit'),
            'image_source' => $this->nullableTrimmed('image_source'),
            'image_rights_confirmed' => $this->boolean('image_rights_confirmed'),
            'remove_image' => $this->boolean('remove_image'),
            'published_at' => $this->nullableTrimmed('published_at'),
            'seo_title' => $this->nullableTrimmed('seo_title'),
            'seo_description' => $this->nullableTrimmed('seo_description'),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'slug' => $this->slugRules(),
            'excerpt' => ['required', 'string', 'max:500'],
            'body' => ['required', 'string', 'max:20000'],
            'image' => [
                'nullable',
                'file',
                'mimetypes:image/jpeg,image/png,image/webp',
                'max:8192',
            ],
            'image_alt' => ['nullable', 'string', 'max:255'],
            'image_credit' => ['nullable', 'string', 'max:255'],
            'image_source' => ['nullable', 'string', 'max:500'],
            'image_rights_confirmed' => ['required', 'boolean'],
            'remove_image' => ['required', 'boolean'],
            'status' => $this->statusRules(),
            'published_at' => ['nullable', 'date_format:Y-m-d\TH:i'],
            'seo_title' => ['nullable', 'string', 'max:255'],
            'seo_description' => ['nullable', 'string', 'max:500'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $this->validatePlainText($validator);
            $this->validateMediaOperation($validator);
            $this->validateImmutableHistory($validator);
            $this->validatePublicationGate($validator);
        });
    }

    /**
     * @return array<int, mixed>
     */
    abstract protected function slugRules(): array;

    /**
     * @return array<int, mixed>
     */
    abstract protected function statusRules(): array;

    protected function commonSlugRules(?NewsArticle $article = null): array
    {
        return [
            'string',
            'max:255',
            'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/',
            Rule::unique('news_articles', 'slug')->ignore($article?->id),
        ];
    }

    protected function statusEnumRules(): array
    {
        return ['required', new Enum(NewsArticleStatus::class)];
    }

    private function validatePlainText(Validator $validator): void
    {
        foreach (['excerpt', 'body'] as $field) {
            if (preg_match('/<\s*\/?\s*[a-z][^>]*>/iu', (string) $this->input($field)) === 1) {
                $validator->errors()->add(
                    $field,
                    'El contenido debe ser texto plano y no puede incluir HTML.'
                );
            }
        }
    }

    private function validateMediaOperation(Validator $validator): void
    {
        if ($this->hasFile('image') && $this->boolean('remove_image')) {
            $validator->errors()->add(
                'remove_image',
                'No puedes sustituir y retirar la imagen en la misma operación.'
            );
        }

        if ($this->boolean('remove_image')
            && $this->input('status') !== NewsArticleStatus::DRAFT->value) {
            $validator->errors()->add(
                'remove_image',
                'Para retirar la imagen debes guardar primero la noticia como borrador.'
            );
        }
    }

    private function validateImmutableHistory(Validator $validator): void
    {
        $article = $this->article();

        if (! $article instanceof NewsArticle || $article->published_at === null) {
            return;
        }

        if ($this->filled('slug') && $this->input('slug') !== $article->slug) {
            $validator->errors()->add(
                'slug',
                'El slug queda bloqueado desde que la noticia se programa o publica.'
            );
        }

        if (! $article->hasBeenEffectivelyPublished() || ! $this->filled('published_at')) {
            return;
        }

        if ($this->input('published_at') !== $article->published_at->format('Y-m-d\TH:i')) {
            $validator->errors()->add(
                'published_at',
                'La fecha histórica no puede cambiar después de la publicación efectiva.'
            );
        }
    }

    private function validatePublicationGate(Validator $validator): void
    {
        if ($this->input('status') !== NewsArticleStatus::PUBLISHED->value) {
            return;
        }

        $article = $this->article();
        $hasImage = $this->hasFile('image')
            || ($article instanceof NewsArticle
                && is_string($article->image_key)
                && ! $this->boolean('remove_image'));

        if (! $hasImage) {
            $validator->errors()->add('image', 'La imagen principal es obligatoria para publicar.');
        }

        if (! $this->filled('image_alt')) {
            $validator->errors()->add(
                'image_alt',
                'El texto alternativo es obligatorio para publicar.'
            );
        }

        if (! $this->filled('image_source')) {
            $validator->errors()->add(
                'image_source',
                'La procedencia de la imagen es obligatoria para publicar.'
            );
        }

        if (! $this->boolean('image_rights_confirmed')) {
            $validator->errors()->add(
                'image_rights_confirmed',
                'Debes confirmar que ya has verificado los derechos y autorizaciones aplicables.'
            );
        }
    }

    private function article(): ?NewsArticle
    {
        $article = $this->route('news_article');

        return $article instanceof NewsArticle ? $article : null;
    }

    private function nullableTrimmed(string $field): ?string
    {
        $value = trim((string) $this->input($field));

        return $value === '' ? null : $value;
    }
}
