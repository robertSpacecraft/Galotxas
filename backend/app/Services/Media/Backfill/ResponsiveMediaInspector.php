<?php

namespace App\Services\Media\Backfill;

use App\Services\Media\ImageFormat;
use App\Services\Media\ImagePreparationPolicy;
use App\Services\Media\ManifestImage;
use App\Services\Media\MediaObjectKeyGenerator;
use App\Services\Media\ResponsiveImageProfile;
use App\Services\Media\ResponsiveManifest;
use App\Services\Media\ResponsiveMediaKeys;
use App\Services\Media\VariantPolicyVersion;
use Aws\Exception\AwsException;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Exception\RequestException;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Filesystem\FilesystemManager;
use InvalidArgumentException;
use JsonException;
use Throwable;

/** Exact-key reads only. Deliberately independent of public cache, logging and fail-soft reads. */
class ResponsiveMediaInspector
{
    public function __construct(private readonly FilesystemManager $filesystems, private readonly ResponsiveMediaKeys $keys) {}

    public function inspectMaster(string $key, ResponsiveImageProfile $profile): ObjectInspection
    {
        $this->assertMaster($key, $profile);

        return $this->read($key, (int) config('media.stored_master_max_bytes'));
    }

    public function inspectManifest(string $masterKey, ResponsiveImageProfile $profile, ImagePreparationPolicy $policy): ManifestInspection
    {
        $this->assertMaster($masterKey, $profile);
        $key = $this->keys->manifest($masterKey, VariantPolicyVersion::V1);
        $object = $this->read($key, ResponsiveManifest::MAX_BYTES);
        if ($object->state !== ObjectInspectionState::Present) {
            return new ManifestInspection(
                $object->state === ObjectInspectionState::Missing ? ManifestInspectionState::Missing : ManifestInspectionState::InspectionFailed,
                reason: $object->reason,
            );
        }
        try {
            $manifest = ResponsiveManifest::fromJson($object->bytes, $masterKey, $key, $this->keys);
        } catch (JsonException) {
            return new ManifestInspection(ManifestInspectionState::Invalid, reason: InspectionReason::InvalidJson);
        } catch (Throwable) {
            return new ManifestInspection(ManifestInspectionState::Invalid, reason: InspectionReason::SchemaViolation);
        }
        if ($manifest->profile !== $profile || $manifest->policy !== $policy) {
            return new ManifestInspection(ManifestInspectionState::Invalid, reason: InspectionReason::IdentityMismatch);
        }

        return new ManifestInspection(ManifestInspectionState::Valid, $manifest);
    }

    /** @return array<string, ObjectInspection> Every allowed width, both formats, including widths >= master. */
    public function inspectTargets(string $masterKey, ResponsiveImageProfile $profile): array
    {
        $this->assertMaster($masterKey, $profile);
        $results = [];
        foreach (VariantPolicyVersion::V1->widths($profile) as $width) {
            foreach ([ImageFormat::Png, ImageFormat::Webp] as $format) {
                $key = $this->keys->variant($masterKey, $profile, VariantPolicyVersion::V1, $width, $format);
                try {
                    $exists = $this->disk()->fileExists($key);
                    $results[$key] = new ObjectInspection($exists ? ObjectInspectionState::Present : ObjectInspectionState::Missing);
                } catch (Throwable $exception) {
                    $results[$key] = new ObjectInspection(ObjectInspectionState::InspectionFailed, reason: $this->failureReason($exception));
                }
            }
        }

        return $results;
    }

    public function inspectVariant(ManifestImage $image, string $masterKey, ResponsiveImageProfile $profile): ObjectInspection
    {
        if (! $this->keys->isValidVariant($image->key, $masterKey, $profile, VariantPolicyVersion::V1,
            $image->width, ImageFormat::fromMimeType($image->mimeType))) {
            throw new InvalidArgumentException('La identidad de variante no es válida.');
        }

        return $this->read($image->key, (int) config('media.stored_master_max_bytes'));
    }

    public function matchesDescriptor(string $bytes, ManifestImage $image): bool
    {
        $header = @getimagesizefromstring($bytes);

        return strlen($bytes) === $image->size && $header !== false
            && $header[0] === $image->width && $header[1] === $image->height
            && $header['mime'] === $image->mimeType
            && (new \finfo(FILEINFO_MIME_TYPE))->buffer($bytes) === $image->mimeType;
    }

    private function read(string $key, int $maxBytes): ObjectInspection
    {
        $stream = null;
        try {
            if ($maxBytes < 1) {
                throw new InvalidArgumentException('El límite de lectura no es válido.');
            }
            $disk = $this->disk();
            if (! $disk->fileExists($key)) {
                return new ObjectInspection(ObjectInspectionState::Missing, reason: InspectionReason::ObjectMissing);
            }
            $size = $disk->size($key);
            $stream = $disk->readStream($key);
            if (! is_resource($stream)) {
                return new ObjectInspection(ObjectInspectionState::InspectionFailed, reason: InspectionReason::TransportError);
            }
            $bytes = '';
            while (! feof($stream) && strlen($bytes) <= $maxBytes) {
                $chunk = fread($stream, min(8192, $maxBytes + 1 - strlen($bytes)));
                if ($chunk === false || ($chunk === '' && ! feof($stream))) {
                    return new ObjectInspection(ObjectInspectionState::InspectionFailed, reason: InspectionReason::TruncatedRead);
                }
                $bytes .= $chunk;
            }
            if (stream_get_meta_data($stream)['timed_out'] ?? false) {
                return new ObjectInspection(ObjectInspectionState::InspectionFailed, reason: InspectionReason::Timeout);
            }
            if (strlen($bytes) <= $maxBytes && strlen($bytes) !== $size) {
                return new ObjectInspection(ObjectInspectionState::InspectionFailed, reason: InspectionReason::TruncatedRead);
            }

            return new ObjectInspection(ObjectInspectionState::Present, $bytes);
        } catch (Throwable $exception) {
            return new ObjectInspection(ObjectInspectionState::InspectionFailed, reason: $this->failureReason($exception));
        } finally {
            if (is_resource($stream)) {
                fclose($stream);
            }
        }
    }

    private function failureReason(Throwable $exception): InspectionReason
    {
        do {
            if ($exception instanceof AwsException && $exception->getStatusCode() === 403
                || $exception instanceof RequestException && $exception->getResponse()?->getStatusCode() === 403) {
                return InspectionReason::AccessDenied;
            }
            if ($exception instanceof ConnectException && ($exception->getHandlerContext()['errno'] ?? null) === 28) {
                return InspectionReason::Timeout;
            }
        } while ($exception = $exception->getPrevious());

        return InspectionReason::TransportError;
    }

    private function assertMaster(string $key, ResponsiveImageProfile $profile): void
    {
        if (! (new MediaObjectKeyGenerator)->isValidForPurpose($key, $profile->purpose())) {
            throw new InvalidArgumentException('La referencia multimedia no es válida.');
        }
    }

    private function disk(): FilesystemAdapter
    {
        $name = trim((string) config('media.disk'));
        if ($name === '') {
            throw new InvalidArgumentException('El disco multimedia no es válido.');
        }

        return $this->filesystems->disk($name);
    }
}
