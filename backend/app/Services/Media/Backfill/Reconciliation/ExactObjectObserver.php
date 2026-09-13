<?php

namespace App\Services\Media\Backfill\Reconciliation;

use App\Services\Media\Backfill\Safety\ObjectKind;
use App\Services\Media\Backfill\Safety\StorageIdentity;
use App\Services\Media\Backfill\Safety\TargetObject;
use App\Services\Media\ManifestImage;
use Illuminate\Filesystem\FilesystemAdapter;
use stdClass;
use Throwable;

/** Exact-key, bounded reads only. It never exposes bytes or storage exceptions. */
class ExactObjectObserver
{
    public function __construct(private readonly StorageObservationCapability $capability) {}

    public function observe(stdClass $row, ?ManifestImage $descriptor = null): ObjectObservation
    {
        try {
            return $this->observeOn($row, $this->capability->currentDisk(), $descriptor)->classification;
        } catch (Throwable) {
            return ObjectObservation::Unreadable;
        }
    }

    public function observeExact(
        stdClass $row,
        StorageIdentity $identity,
        ReconciliationBackendMode $backendMode,
        ?ManifestImage $descriptor = null,
    ): ExactObjectObservation {
        try {
            return $this->observeOn($row, $this->capability->disk($identity, $backendMode), $descriptor);
        } catch (Throwable) {
            return ExactObjectObservation::unreadable();
        }
    }

    private function observeOn(
        stdClass $row,
        FilesystemAdapter $disk,
        ?ManifestImage $descriptor,
    ): ExactObjectObservation {
        $stream = null;
        try {
            $target = new TargetObject(
                $row->object_key,
                ObjectKind::from($row->kind),
                $row->expected_sha256,
                (int) $row->expected_size,
                $row->mime_type,
            );
            if (! $disk->fileExists($target->key)) {
                return ExactObjectObservation::absentNow();
            }
            if ($disk->size($target->key) !== $target->size) {
                return ExactObjectObservation::differentContentPresent();
            }
            $stream = $disk->readStream($target->key);
            if (! is_resource($stream)) {
                return ExactObjectObservation::unreadable();
            }

            $bytes = '';
            while (! feof($stream) && strlen($bytes) <= $target->size) {
                $chunk = fread($stream, min(8192, $target->size + 1 - strlen($bytes)));
                if ($chunk === false || ($chunk === '' && ! feof($stream))) {
                    return ExactObjectObservation::unreadable();
                }
                $bytes .= $chunk;
            }
            if (stream_get_meta_data($stream)['timed_out'] ?? false) {
                return ExactObjectObservation::unreadable();
            }
            if (strlen($bytes) !== $target->size) {
                return ExactObjectObservation::unreadable();
            }
            if (! hash_equals($target->sha256, hash('sha256', $bytes))) {
                return ExactObjectObservation::differentContentPresent();
            }

            try {
                $target->validateBytes($bytes);
            } catch (Throwable) {
                return ExactObjectObservation::differentContentPresent();
            }
            if ($descriptor !== null && ! $this->matchesDescriptor($bytes, $descriptor)) {
                return ExactObjectObservation::differentContentPresent();
            }

            $mimeType = $target->kind === ObjectKind::Manifest
                ? 'application/json'
                : (new \finfo(FILEINFO_MIME_TYPE))->buffer($bytes);
            if (! is_string($mimeType) || $mimeType !== $target->mimeType) {
                return ExactObjectObservation::differentContentPresent();
            }

            return ExactObjectObservation::expectedContentPresent(
                hash('sha256', $bytes),
                strlen($bytes),
                $mimeType,
                $target->kind === ObjectKind::Manifest || $descriptor !== null,
                true,
            );
        } catch (Throwable) {
            return ExactObjectObservation::unreadable();
        } finally {
            if (is_resource($stream)) {
                fclose($stream);
            }
        }
    }

    private function matchesDescriptor(string $bytes, ManifestImage $descriptor): bool
    {
        $header = @getimagesizefromstring($bytes);

        return $header !== false
            && strlen($bytes) === $descriptor->size
            && $header[0] === $descriptor->width
            && $header[1] === $descriptor->height
            && ($header['mime'] ?? null) === $descriptor->mimeType
            && (new \finfo(FILEINFO_MIME_TYPE))->buffer($bytes) === $descriptor->mimeType;
    }
}
