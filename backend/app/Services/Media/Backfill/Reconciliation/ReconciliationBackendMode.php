<?php

namespace App\Services\Media\Backfill\Reconciliation;

enum ReconciliationBackendMode: string
{
    case Local = 'local';
    case S3 = 's3';

    /** Mirrors the disks accepted by StorageIdentity; anything else is unsupported. */
    public static function current(): self
    {
        return match (trim((string) config('media.disk'))) {
            'media_local' => self::Local,
            'media_s3' => self::S3,
            default => throw new ReconciliationException(ReconciliationError::UnsupportedStorage),
        };
    }
}
