<?php

namespace App\Services;

use App\Models\Category;
use App\Models\Championship;
use App\Models\Season;
use App\Services\Media\Exceptions\InvalidMediaImage;
use App\Services\Media\Exceptions\MediaStorageException;
use App\Services\Media\ImageNormalizer;
use App\Services\Media\MediaObjectKeyGenerator;
use App\Services\Media\MediaPurpose;
use App\Services\Media\MediaStorageService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Throwable;

class CompetitionImageService
{
    public function __construct(
        private readonly ImageNormalizer $images,
        private readonly MediaStorageService $storage,
        private readonly MediaObjectKeyGenerator $keys,
    ) {}

    /**
     * The domain callback locks existing rows before assigning the image and saving.
     * Storage is outside transaction retries; a failed mutation compensates only its new object.
     *
     * @template T of Season|Championship|Category
     *
     * @param  callable(?string): T  $mutation
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
            $newKey = $image === null ? null : $this->storage->store(
                MediaPurpose::Banner,
                $this->images->normalize($image, 'banner'),
            );
        } catch (InvalidMediaImage $exception) {
            throw ValidationException::withMessages(['image' => $exception->getMessage()]);
        } catch (MediaStorageException) {
            throw ValidationException::withMessages([
                'image' => 'No se pudo guardar la imagen. Inténtalo de nuevo más tarde.',
            ]);
        }

        try {
            return DB::transaction(fn () => $mutation($newKey), 3);
        } catch (Throwable $exception) {
            $this->cleanup($newKey, 'mutation_compensation');

            throw $exception;
        }
    }

    /** Save a new model or a freshly read, locked row, inside the domain transaction. */
    public function saveWithImage(Season|Championship|Category $entity, ?string $newKey, bool $removeImage): void
    {
        $oldKey = null;
        if ($newKey !== null || $removeImage) {
            $oldKey = $entity->image_path;
            $entity->image_path = $newKey;
        }

        if (! $entity->save()) {
            throw new RuntimeException('No se pudo guardar la entidad de competición.');
        }

        $this->cleanupAfterCommit($oldKey);
    }

    public function cleanupAfterCommit(mixed $key): void
    {
        if ($this->isManaged($key)) {
            DB::afterCommit(fn () => $this->cleanup($key, 'committed_cleanup'));
        }
    }

    public function isManaged(mixed $key): bool
    {
        return is_string($key) && $this->keys->isValidForPurpose($key, MediaPurpose::Banner);
    }

    /** No storage or visibility queries: public callers already filter effective visibility. */
    public function publicImage(Season|Championship|Category $entity): ?array
    {
        return $this->isManaged($entity->image_path)
            ? ['url' => route('api.v1.'.$entity->getTable().'.image', $entity)]
            : null;
    }

    private function cleanup(mixed $key, string $operation): void
    {
        if (! $this->isManaged($key)) {
            return;
        }

        try {
            $this->storage->delete($key);
        } catch (MediaStorageException) {
            Log::warning('Competition image cleanup failed.', ['operation' => $operation]);
        }
    }
}
