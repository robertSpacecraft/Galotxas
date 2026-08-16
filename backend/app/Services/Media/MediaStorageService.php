<?php

namespace App\Services\Media;

use App\Services\Media\Exceptions\MediaStorageException;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Filesystem\FilesystemManager;
use Throwable;

class MediaStorageService
{
    public function __construct(
        private readonly FilesystemManager $filesystems,
        private readonly MediaObjectKeyGenerator $keys,
    ) {}

    public function store(MediaPurpose $purpose, NormalizedImage $image): string
    {
        $key = $this->keys->generate($purpose, $image->extension);

        try {
            $stored = $this->disk()->put($key, $image->bytes, [
                'visibility' => 'private',
                'ContentType' => $image->mimeType,
            ]);
        } catch (Throwable) {
            throw $this->storageFailure('almacenar');
        }

        if (! $stored) {
            throw $this->storageFailure('almacenar');
        }

        return $key;
    }

    public function exists(string $key): bool
    {
        $this->assertValidKey($key);

        try {
            return $this->disk()->exists($key);
        } catch (Throwable) {
            throw $this->storageFailure('comprobar');
        }
    }

    /**
     * @return array{size: int, mime_type: string, last_modified: int}
     */
    public function metadata(string $key): array
    {
        $this->assertValidKey($key);

        try {
            $disk = $this->disk();

            $mimeType = $disk->mimeType($key);

            if (! is_string($mimeType)) {
                throw $this->storageFailure('leer los metadatos de');
            }

            return [
                'size' => $disk->size($key),
                'mime_type' => $mimeType,
                'last_modified' => $disk->lastModified($key),
            ];
        } catch (Throwable) {
            throw $this->storageFailure('leer los metadatos de');
        }
    }

    /**
     * @return resource
     */
    public function readStream(string $key)
    {
        $this->assertValidKey($key);

        try {
            $stream = $this->disk()->readStream($key);
        } catch (Throwable) {
            throw $this->storageFailure('leer');
        }

        if (! is_resource($stream)) {
            throw $this->storageFailure('leer');
        }

        return $stream;
    }

    public function delete(string $key): void
    {
        $this->assertValidKey($key);

        try {
            $disk = $this->disk();

            if ($disk->exists($key) && ! $disk->delete($key)) {
                throw $this->storageFailure('eliminar');
            }
        } catch (MediaStorageException $exception) {
            throw $exception;
        } catch (Throwable) {
            throw $this->storageFailure('eliminar');
        }
    }

    public function temporaryUrl(string $key, bool $private = false): string
    {
        $this->assertValidKey($key);
        $ttlConfig = $private
            ? 'media.private_temporary_url_ttl_seconds'
            : 'media.temporary_url_ttl_seconds';
        $ttl = max(1, (int) config($ttlConfig));

        try {
            return $this->disk()->temporaryUrl($key, now()->addSeconds($ttl));
        } catch (Throwable) {
            throw $this->storageFailure('generar una URL temporal para');
        }
    }

    private function disk(): FilesystemAdapter
    {
        $disk = trim((string) config('media.disk'));

        if ($disk === '') {
            throw $this->storageFailure('resolver el disco de');
        }

        return $this->filesystems->disk($disk);
    }

    private function assertValidKey(string $key): void
    {
        if (! $this->keys->isValid($key)) {
            throw new MediaStorageException('La clave multimedia no es válida.');
        }
    }

    private function storageFailure(string $operation): MediaStorageException
    {
        return new MediaStorageException("No se pudo {$operation} el recurso multimedia.");
    }
}
