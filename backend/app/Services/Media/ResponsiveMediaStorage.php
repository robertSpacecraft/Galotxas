<?php

namespace App\Services\Media;

use App\Services\Media\Exceptions\MediaStorageException;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Filesystem\FilesystemManager;
use Psr\Log\LoggerInterface;
use Throwable;

class ResponsiveMediaStorage
{
    public function __construct(
        private readonly FilesystemManager $filesystems,
        private readonly MediaObjectKeyGenerator $masters,
        private readonly ResponsiveMediaKeys $keys,
        private readonly LoggerInterface $logger,
    ) {}

    public function store(PreparedResponsiveSet $set): StoredResponsiveSet
    {
        $attempted = [];
        $disk = null;

        try {
            $masterKey = $this->masters->generate($set->profile->purpose(), $set->master->extension);
            $manifest = ResponsiveManifest::fromPrepared($masterKey, $set, $this->keys);
            $manifestKey = $this->keys->manifest($masterKey, $set->version);
            $objects = [$masterKey => [$set->master->bytes, $set->master->mimeType]];
            foreach ($manifest->variants as $index => $variant) {
                $objects[$variant->key] = [$set->variants[$index]->bytes, $variant->mimeType];
            }
            // Insertion order is the publication protocol: manifest is always last.
            $objects[$manifestKey] = [$manifest->toJson(), 'application/json'];
            $disk = $this->disk();

            // Refuse a collision before any write or compensation can touch old objects.
            foreach (array_keys($objects) as $key) {
                if ($disk->exists($key)) {
                    throw new MediaStorageException('La identidad multimedia ya existe.');
                }
            }
            foreach ($objects as $key => [$bytes, $mimeType]) {
                // A failed put may have written bytes (e.g. failure while applying visibility).
                $attempted[] = $key;
                if (! $disk->put($key, $bytes, ['visibility' => 'private', 'ContentType' => $mimeType])) {
                    throw new MediaStorageException('No se pudo almacenar el recurso multimedia.');
                }
            }

            return new StoredResponsiveSet($masterKey, $manifestKey, $manifest);
        } catch (Throwable) {
            if ($disk !== null) {
                // Remove the publication marker first, including an ambiguous failed put.
                foreach (array_reverse($attempted) as $key) {
                    try {
                        if (! $disk->delete($key)) {
                            $this->warning('cleanup');
                        }
                    } catch (Throwable) {
                        $this->warning('cleanup');
                    }
                }
            }

            throw new MediaStorageException('No se pudo almacenar el conjunto multimedia.');
        }
    }

    /** Legacy, corrupt and transient failures all degrade to master-only consumption. */
    public function readManifest(string $masterKey): ?ResponsiveManifest
    {
        if (! $this->masters->isValid($masterKey)) {
            throw new MediaStorageException('La clave multimedia no es válida.');
        }

        $stream = null;
        try {
            $manifestKey = $this->keys->manifest($masterKey, VariantPolicyVersion::V1);
            $disk = $this->disk();
            if (! $disk->exists($manifestKey)) {
                return null;
            }

            $stream = $disk->readStream($manifestKey);
            if (! is_resource($stream)) {
                throw new MediaStorageException('No se pudo leer el manifiesto multimedia.');
            }
            $json = stream_get_contents($stream, ResponsiveManifest::MAX_BYTES + 1);
            if (! is_string($json)) {
                throw new MediaStorageException('No se pudo leer el manifiesto multimedia.');
            }

            return ResponsiveManifest::fromJson($json, $masterKey, $manifestKey, $this->keys);
        } catch (Throwable) {
            $this->warning('read_manifest');

            return null;
        } finally {
            if (is_resource($stream)) {
                fclose($stream);
            }
        }
    }

    private function disk(): FilesystemAdapter
    {
        $name = trim((string) config('media.disk'));
        if ($name === '') {
            throw new MediaStorageException('No se pudo resolver el disco multimedia.');
        }

        return $this->filesystems->disk($name);
    }

    private function warning(string $operation): void
    {
        try {
            $this->logger->warning('Responsive media operation failed.', ['operation' => $operation]);
        } catch (Throwable) {
            // Logging outages must not mask the original failure or break a list response.
        }
    }
}
