<?php

namespace App\Services;

use App\Models\Category;
use App\Models\Championship;
use App\Models\Season;
use App\Services\Media\Exceptions\InvalidMediaImage;
use App\Services\Media\Exceptions\MediaStorageException;
use App\Services\Media\ImagePreparationPolicy;
use App\Services\Media\MediaObjectKeyGenerator;
use App\Services\Media\MediaPurpose;
use App\Services\Media\ResponsiveImagePreparer;
use App\Services\Media\ResponsiveImageProfile;
use App\Services\Media\ResponsiveMediaLifecycle;
use App\Services\Media\ResponsiveMediaResolver;
use App\Services\Media\ResponsiveMediaStorage;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class CompetitionImageService
{
    public function __construct(
        private readonly ResponsiveImagePreparer $images,
        private readonly ResponsiveMediaStorage $storage,
        private readonly MediaObjectKeyGenerator $keys,
        private readonly ResponsiveMediaLifecycle $lifecycle,
        private readonly ResponsiveMediaResolver $resolver,
    ) {}

    /**
     * The domain callback locks existing rows before assigning the image and saving.
     * Storage is outside transaction retries; a failed mutation compensates only its new object.
     *
     * @template T of Season|Championship|Category
     *
     * @param  callable(?string, callable): T  $mutation
     * @return T
     */
    public function mutate(?UploadedFile $image, bool $removeImage, callable $mutation): Season|Championship|Category
    {
        if ($image !== null && $removeImage) {
            throw ValidationException::withMessages([
                'image' => 'No puedes subir una imagen nueva y retirarla en la misma operación.',
            ]);
        }

        try {
            $set = $image === null ? null : $this->storage->store($this->images->prepare(
                $image, ResponsiveImageProfile::Banner, ImagePreparationPolicy::Photo,
            ));
        } catch (InvalidMediaImage $exception) {
            throw ValidationException::withMessages(['image' => $exception->getMessage()]);
        } catch (MediaStorageException) {
            throw ValidationException::withMessages([
                'image' => 'No se pudo guardar la imagen. Inténtalo de nuevo más tarde.',
            ]);
        }

        return $this->lifecycle->mutate($set, fn (callable $obsolete) => $mutation($set?->masterKey, $obsolete), 3);
    }

    /** Save a new model or a freshly read, locked row, inside the domain transaction. */
    public function saveWithImage(Season|Championship|Category $entity, ?string $newKey, bool $removeImage, callable $obsolete): void
    {
        $oldKey = null;
        if ($newKey !== null || $removeImage) {
            $oldKey = $entity->image_path;
            $entity->image_path = $newKey;
        }

        if (! $entity->save()) {
            throw new RuntimeException('No se pudo guardar la entidad de competición.');
        }

        $obsolete($oldKey, ResponsiveImageProfile::Banner);
    }

    public function cleanupAfterCommit(mixed $key): void
    {
        if ($this->isManaged($key)) {
            $this->lifecycle->cleanupAfterCommit($key, ResponsiveImageProfile::Banner);
        }
    }

    public function isManaged(mixed $key): bool
    {
        return is_string($key) && $this->keys->isValidForPurpose($key, MediaPurpose::Banner);
    }

    /** Public callers already filter effective visibility; a manifest is optional. */
    public function publicImage(Season|Championship|Category $entity): ?array
    {
        if (! $this->isManaged($entity->image_path)) {
            return null;
        }

        return $this->resolver->image(
            $entity->image_path,
            ResponsiveImageProfile::Banner,
            route('api.v1.'.$entity->getTable().'.image', $entity),
            fn (int $width): string => route(
                'api.v1.'.$entity->getTable().'.image.variant',
                [$entity, $width],
            ),
        );
    }
}
