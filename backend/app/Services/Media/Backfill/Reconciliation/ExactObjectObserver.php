<?php

namespace App\Services\Media\Backfill\Reconciliation;

use App\Services\Media\Backfill\Safety\ObjectKind;
use App\Services\Media\Backfill\Safety\TargetObject;
use App\Services\Media\ManifestImage;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Filesystem\FilesystemManager;
use InvalidArgumentException;
use stdClass;
use Throwable;

/** Exact-key, bounded reads only. It never exposes bytes or storage exceptions. */
class ExactObjectObserver
{
    public function __construct(private readonly FilesystemManager $filesystems) {}

    public function observe(stdClass $row, ?ManifestImage $descriptor = null): ObjectObservation
    {
        $stream = null;
        try {
            $target = new TargetObject(
                $row->object_key,
                ObjectKind::from($row->kind),
                $row->expected_sha256,
                (int) $row->expected_size,
                $row->mime_type,
            );
            $disk = $this->disk();
            if (! $disk->fileExists($target->key)) {
                return ObjectObservation::AbsentNow;
            }
            if ($disk->size($target->key) !== $target->size) {
                return ObjectObservation::DifferentContentPresent;
            }
            $stream = $disk->readStream($target->key);
            if (! is_resource($stream)) {
                return ObjectObservation::Unreadable;
            }

            $bytes = '';
            while (! feof($stream) && strlen($bytes) <= $target->size) {
                $chunk = fread($stream, min(8192, $target->size + 1 - strlen($bytes)));
                if ($chunk === false || ($chunk === '' && ! feof($stream))) {
                    return ObjectObservation::Unreadable;
                }
                $bytes .= $chunk;
            }
            if ((stream_get_meta_data($stream)['timed_out'] ?? false)
                || strlen($bytes) !== $target->size
                || ! hash_equals($target->sha256, hash('sha256', $bytes))) {
                return strlen($bytes) === $target->size
                    ? ObjectObservation::DifferentContentPresent
                    : ObjectObservation::Unreadable;
            }

            try {
                $target->validateBytes($bytes);
            } catch (Throwable) {
                return ObjectObservation::DifferentContentPresent;
            }
            if ($descriptor !== null && ! $this->matchesDescriptor($bytes, $descriptor)) {
                return ObjectObservation::DifferentContentPresent;
            }

            return ObjectObservation::ExpectedContentPresent;
        } catch (Throwable) {
            return ObjectObservation::Unreadable;
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

    private function disk(): FilesystemAdapter
    {
        $name = trim((string) config('media.disk'));
        if ($name === '') {
            throw new InvalidArgumentException('Invalid media disk.');
        }

        return $this->filesystems->disk($name);
    }
}
