<?php

namespace App\Services;

use App\Enums\NewsArticleStatus;
use App\Models\NewsArticle;
use App\Models\User;
use App\Services\Media\Exceptions\MediaStorageException;
use App\Services\Media\ImageNormalizer;
use App\Services\Media\MediaObjectKeyGenerator;
use App\Services\Media\MediaPurpose;
use App\Services\Media\MediaStorageService;
use Carbon\CarbonImmutable;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

class NewsArticleService
{
    public function __construct(
        private readonly ImageNormalizer $images,
        private readonly MediaStorageService $storage,
        private readonly MediaObjectKeyGenerator $keys,
    ) {}

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function create(
        array $attributes,
        ?UploadedFile $image,
        User $actor
    ): NewsArticle {
        $normalized = $image === null
            ? null
            : $this->images->normalize($image, 'news_cover');
        $newKey = $normalized === null
            ? null
            : $this->storage->store(MediaPurpose::News, $normalized);

        try {
            return DB::transaction(function () use (
                $actor,
                $attributes,
                $newKey,
                $normalized
            ): NewsArticle {
                $articleAttributes = $this->editorialAttributes($attributes);
                $articleAttributes['slug'] = $this->createSlug(
                    $attributes['slug'] ?? null,
                    $articleAttributes['title']
                );
                $articleAttributes['status'] = NewsArticleStatus::DRAFT->value;
                $articleAttributes['published_at'] = null;

                if ($newKey !== null && $normalized !== null) {
                    $articleAttributes = [
                        ...$articleAttributes,
                        ...$this->newImageAttributes(
                            $attributes,
                            $newKey,
                            $normalized->width,
                            $normalized->height,
                            $actor
                        ),
                    ];
                }

                return NewsArticle::query()->create($articleAttributes);
            });
        } catch (Throwable $exception) {
            if ($newKey !== null) {
                $this->cleanup($newKey, 'create_compensation');
            }

            throw $exception;
        }
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function update(
        NewsArticle $article,
        array $attributes,
        ?UploadedFile $image,
        User $actor
    ): NewsArticle {
        $removeImage = (bool) ($attributes['remove_image'] ?? false);

        if ($removeImage && $image !== null) {
            throw ValidationException::withMessages([
                'remove_image' => 'No puedes sustituir y retirar la imagen en la misma operación.',
            ]);
        }

        $normalized = $image === null
            ? null
            : $this->images->normalize($image, 'news_cover');
        $newKey = $normalized === null
            ? null
            : $this->storage->store(MediaPurpose::News, $normalized);
        $oldKey = null;

        try {
            $updated = DB::transaction(function () use (
                $actor,
                $article,
                $attributes,
                $newKey,
                $normalized,
                $removeImage,
                &$oldKey
            ): NewsArticle {
                $locked = NewsArticle::query()->lockForUpdate()->findOrFail($article->getKey());
                $oldKey = $locked->image_key;
                $next = [
                    ...$this->editorialAttributes($attributes),
                    'slug' => $this->updatedSlug($locked, (string) $attributes['slug']),
                    'status' => (string) $attributes['status'],
                    'published_at' => $this->nextPublishedAt($locked, $attributes),
                ];

                if ($newKey !== null && $normalized !== null) {
                    $next = [
                        ...$next,
                        ...$this->newImageAttributes(
                            $attributes,
                            $newKey,
                            $normalized->width,
                            $normalized->height,
                            $actor
                        ),
                    ];
                } elseif ($removeImage) {
                    if ($next['status'] !== NewsArticleStatus::DRAFT->value) {
                        throw ValidationException::withMessages([
                            'remove_image' => 'Para retirar la imagen debes guardar la noticia como borrador.',
                        ]);
                    }

                    $next = [...$next, ...$this->emptyImageAttributes()];
                } else {
                    $next['image_alt'] = $attributes['image_alt'] ?? null;
                    $next['image_credit'] = $attributes['image_credit'] ?? null;
                    $next['image_source'] = $attributes['image_source'] ?? null;

                    if ((bool) ($attributes['image_rights_confirmed'] ?? false)) {
                        $next['image_rights_confirmed_at'] = now();
                        $next['image_rights_confirmed_by'] = $actor->getKey();
                    }
                }

                $this->assertPublishable($locked, $next, $attributes);
                $locked->fill($next)->save();

                return $locked;
            });
        } catch (Throwable $exception) {
            if ($newKey !== null) {
                $this->cleanup($newKey, 'replace_compensation');
            }

            throw $exception;
        }

        if (($newKey !== null || $removeImage) && is_string($oldKey)) {
            $this->cleanup($oldKey, $newKey !== null ? 'replace_old_object' : 'remove_object');
        }

        return $updated;
    }

    public function delete(NewsArticle $article): void
    {
        $oldKey = null;

        DB::transaction(function () use ($article, &$oldKey): void {
            $locked = NewsArticle::query()->lockForUpdate()->findOrFail($article->getKey());
            $oldKey = $locked->image_key;
            $locked->delete();
        });

        if (is_string($oldKey)) {
            $this->cleanup($oldKey, 'delete_object');
        }
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    private function editorialAttributes(array $attributes): array
    {
        return Arr::only($attributes, [
            'title',
            'excerpt',
            'body',
            'seo_title',
            'seo_description',
        ]);
    }

    private function createSlug(mixed $requested, string $title): string
    {
        if (is_string($requested) && $requested !== '') {
            if (NewsArticle::withTrashed()->where('slug', $requested)->exists()) {
                throw ValidationException::withMessages([
                    'slug' => 'El slug ya está en uso, incluso por una noticia eliminada.',
                ]);
            }

            return $requested;
        }

        $base = Str::slug($title) ?: 'noticia';
        $slug = $base;
        $suffix = 2;

        while (NewsArticle::withTrashed()->where('slug', $slug)->exists()) {
            $slug = $base.'-'.$suffix;
            $suffix++;
        }

        return $slug;
    }

    private function updatedSlug(NewsArticle $article, string $requested): string
    {
        if (! $article->isSlugEditable()) {
            if ($requested !== $article->slug) {
                throw ValidationException::withMessages([
                    'slug' => 'El slug queda bloqueado desde que la noticia se programa o publica.',
                ]);
            }

            return $article->slug;
        }

        $exists = NewsArticle::withTrashed()
            ->where('slug', $requested)
            ->whereKeyNot($article->getKey())
            ->exists();

        if ($exists) {
            throw ValidationException::withMessages([
                'slug' => 'El slug ya está en uso, incluso por una noticia eliminada.',
            ]);
        }

        return $requested;
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function nextPublishedAt(
        NewsArticle $article,
        array $attributes
    ): ?CarbonImmutable {
        $status = (string) $attributes['status'];
        $requested = $this->parseDate($attributes['published_at'] ?? null);

        if ($article->hasBeenEffectivelyPublished()) {
            return $article->published_at;
        }

        if ($status === NewsArticleStatus::DRAFT->value) {
            if ($article->published_at === null) {
                return null;
            }

            return $requested?->isFuture() === true ? $requested : $article->published_at;
        }

        return $requested?->isFuture() === true ? $requested : CarbonImmutable::now();
    }

    private function parseDate(mixed $value): ?CarbonImmutable
    {
        if (! is_string($value) || $value === '') {
            return null;
        }

        return CarbonImmutable::createFromFormat(
            'Y-m-d\TH:i',
            $value,
            (string) config('app.timezone')
        );
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    private function newImageAttributes(
        array $attributes,
        string $key,
        int $width,
        int $height,
        User $actor
    ): array {
        $confirmed = (bool) ($attributes['image_rights_confirmed'] ?? false);

        return [
            'image_key' => $key,
            'image_width' => $width,
            'image_height' => $height,
            'image_alt' => $attributes['image_alt'] ?? null,
            'image_credit' => $attributes['image_credit'] ?? null,
            'image_source' => $attributes['image_source'] ?? null,
            'image_rights_confirmed_at' => $confirmed ? now() : null,
            'image_rights_confirmed_by' => $confirmed ? $actor->getKey() : null,
        ];
    }

    /**
     * @return array<string, null>
     */
    private function emptyImageAttributes(): array
    {
        return [
            'image_key' => null,
            'image_width' => null,
            'image_height' => null,
            'image_alt' => null,
            'image_credit' => null,
            'image_source' => null,
            'image_rights_confirmed_at' => null,
            'image_rights_confirmed_by' => null,
        ];
    }

    /**
     * @param  array<string, mixed>  $next
     * @param  array<string, mixed>  $requestAttributes
     */
    private function assertPublishable(
        NewsArticle $article,
        array $next,
        array $requestAttributes
    ): void {
        if (($next['status'] ?? null) !== NewsArticleStatus::PUBLISHED->value) {
            return;
        }

        $errors = [];
        $imageKey = $next['image_key'] ?? $article->image_key;

        if (! is_string($imageKey)
            || ! $this->keys->isValidForPurpose($imageKey, MediaPurpose::News)) {
            $errors['image'] = 'La imagen principal es obligatoria para publicar.';
        }

        if (! is_string($next['image_alt'] ?? null) || $next['image_alt'] === '') {
            $errors['image_alt'] = 'El texto alternativo es obligatorio para publicar.';
        }

        if (! is_string($next['image_source'] ?? null) || $next['image_source'] === '') {
            $errors['image_source'] = 'La procedencia de la imagen es obligatoria para publicar.';
        }

        if (! (bool) ($requestAttributes['image_rights_confirmed'] ?? false)) {
            $errors['image_rights_confirmed'] = 'Debes confirmar que ya has verificado los derechos y autorizaciones aplicables.';
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }
    }

    private function cleanup(string $key, string $operation): void
    {
        if (! $this->keys->isValidForPurpose($key, MediaPurpose::News)) {
            Log::warning('News media cleanup skipped for an invalid reference.', [
                'operation' => $operation,
            ]);

            return;
        }

        try {
            $this->storage->delete($key);
        } catch (MediaStorageException) {
            Log::warning('News media cleanup failed.', [
                'operation' => $operation,
            ]);
        }
    }
}
