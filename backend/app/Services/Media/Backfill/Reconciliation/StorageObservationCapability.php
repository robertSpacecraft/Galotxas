<?php

namespace App\Services\Media\Backfill\Reconciliation;

use App\Services\Media\Backfill\Safety\BackfillSafetyException;
use App\Services\Media\Backfill\Safety\SafetyError;
use App\Services\Media\Backfill\Safety\StorageIdentity;
use Aws\S3\S3Client;
use Illuminate\Database\DatabaseManager;
use Illuminate\Filesystem\AwsS3V3Adapter;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Filesystem\FilesystemManager;
use League\Flysystem\AwsS3V3\AwsS3V3Adapter as FlysystemS3Adapter;
use League\Flysystem\Local\LocalFilesystemAdapter;
use Throwable;

/** Read-only runtime gate for the exact media topology and installed Flysystem adapters. */
class StorageObservationCapability
{
    private const PROBE_KEY = 'variants/v1/news/550e8400-e29b-41d4-a716-446655440000/manifest.json';

    public function __construct(
        private readonly FilesystemManager $filesystems,
        private readonly DatabaseManager $database,
    ) {}

    public function currentDisk(): FilesystemAdapter
    {
        try {
            return $this->disk(StorageIdentity::current($this->database), ReconciliationBackendMode::current());
        } catch (BackfillSafetyException $error) {
            throw new ReconciliationException(
                $error->reason === SafetyError::IdentityMismatch
                    ? ReconciliationError::IdentityMismatch
                    : ReconciliationError::UnsupportedStorage,
            );
        } catch (ReconciliationException $error) {
            throw $error;
        } catch (Throwable) {
            throw new ReconciliationException(ReconciliationError::UnsupportedStorage);
        }
    }

    public function disk(StorageIdentity $identity, ReconciliationBackendMode $mode): FilesystemAdapter
    {
        try {
            $current = StorageIdentity::current($this->database);
            if (! hash_equals($current->hash, $identity->hash)) {
                throw new ReconciliationException(ReconciliationError::IdentityMismatch);
            }
            $diskName = match ($mode) {
                ReconciliationBackendMode::Local => 'media_local',
                ReconciliationBackendMode::S3 => 'media_s3',
            };
            if (trim((string) config('media.disk')) !== $diskName
                || ReconciliationBackendMode::current() !== $mode) {
                throw new ReconciliationException(ReconciliationError::UnsupportedStorage);
            }

            $disk = $this->filesystems->disk($diskName);
            $runtime = $disk->getConfig();
            $configured = config('filesystems.disks.'.$diskName, []);
            if (! is_array($configured)) {
                throw new ReconciliationException(ReconciliationError::UnsupportedStorage);
            }
            foreach (['driver', 'root', 'prefix', 'bucket', 'endpoint', 'region',
                'use_path_style_endpoint', 'visibility'] as $field) {
                if (($runtime[$field] ?? null) !== ($configured[$field] ?? null)) {
                    throw new ReconciliationException(ReconciliationError::UnsupportedStorage);
                }
            }
            if (($runtime['visibility'] ?? null) !== 'private' || ! empty($runtime['prefix'])
                || ! empty($runtime['read-only'])
                || ! method_exists($disk, 'fileExists') || ! method_exists($disk, 'size')
                || ! method_exists($disk, 'readStream')) {
                throw new ReconciliationException(ReconciliationError::UnsupportedStorage);
            }

            if ($mode === ReconciliationBackendMode::Local) {
                $root = realpath(is_string($runtime['root'] ?? null) ? $runtime['root'] : '');
                $configuredRoot = realpath(is_string($configured['root'] ?? null) ? $configured['root'] : '');
                if (($runtime['driver'] ?? null) !== 'local'
                    || ! $disk instanceof FilesystemAdapter || $disk instanceof AwsS3V3Adapter
                    || get_class($disk->getAdapter()) !== LocalFilesystemAdapter::class
                    || $root === false || $configuredRoot === false || $root !== $configuredRoot
                    || ! is_dir($root)
                    || $disk->path(self::PROBE_KEY) !== $root.'/'.self::PROBE_KEY) {
                    throw new ReconciliationException(ReconciliationError::UnsupportedStorage);
                }

                return $disk;
            }

            if (($runtime['driver'] ?? null) !== 's3' || ! $disk instanceof AwsS3V3Adapter
                || get_class($disk->getAdapter()) !== FlysystemS3Adapter::class
                || ! $disk->getClient() instanceof S3Client || empty($runtime['bucket'])
                || ! empty($runtime['root']) || $disk->path(self::PROBE_KEY) !== self::PROBE_KEY) {
                throw new ReconciliationException(ReconciliationError::UnsupportedStorage);
            }
            $disk->getClient()->getApi()->getOperation('HeadObject');
            $disk->getClient()->getApi()->getOperation('GetObject');

            return $disk;
        } catch (BackfillSafetyException $error) {
            throw new ReconciliationException(
                $error->reason === SafetyError::IdentityMismatch
                    ? ReconciliationError::IdentityMismatch
                    : ReconciliationError::UnsupportedStorage,
            );
        } catch (ReconciliationException $error) {
            throw $error;
        } catch (Throwable) {
            throw new ReconciliationException(ReconciliationError::UnsupportedStorage);
        }
    }
}
