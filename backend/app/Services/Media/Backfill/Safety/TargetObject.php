<?php

namespace App\Services\Media\Backfill\Safety;

use App\Services\Media\ImageFormat;
use App\Services\Media\MediaObjectKeyGenerator;
use App\Services\Media\ResponsiveImageProfile;
use App\Services\Media\ResponsiveManifest;
use App\Services\Media\ResponsiveMediaKeys;
use App\Services\Media\VariantPolicyVersion;
use Throwable;

final readonly class TargetObject
{
    public function __construct(
        public string $key,
        public ObjectKind $kind,
        public string $sha256,
        public int $size,
        public string $mimeType,
    ) {
        JournalPayload::sha256($sha256);
        $this->validateKey();
        $max = $kind === ObjectKind::Manifest ? ResponsiveManifest::MAX_BYTES : (int) config('media.stored_master_max_bytes');
        $mime = str_ends_with($key, '.json') ? 'application/json' : (str_ends_with($key, '.png') ? 'image/png' : 'image/webp');
        if ($size < 1 || $size > $max || $mimeType !== $mime) {
            throw new BackfillSafetyException(SafetyError::InvalidInput);
        }
    }

    public function identity(): string
    {
        $parts = explode('/', $this->key);

        return $parts[2].'/'.$parts[3];
    }

    private function validateKey(): void
    {
        $parts = explode('/', $this->key);
        if (count($parts) !== 5 || $parts[0] !== 'variants' || $parts[1] !== 'v1') {
            throw new BackfillSafetyException(SafetyError::InvalidInput);
        }
        $master = $parts[2].'/'.$parts[3].'.webp';
        $keys = new ResponsiveMediaKeys(new MediaObjectKeyGenerator);
        foreach (ResponsiveImageProfile::cases() as $profile) {
            if ($profile->purpose()->value !== $parts[2]) {
                continue;
            }
            if ($this->kind === ObjectKind::Manifest && $keys->isValidManifest($this->key, $master, VariantPolicyVersion::V1)) {
                return;
            }
            if ($this->kind === ObjectKind::Variant && preg_match('/\Aw([1-9][0-9]*)\.(png|webp)\z/', $parts[4], $match)
                && $keys->isValidVariant($this->key, $master, $profile, VariantPolicyVersion::V1, (int) $match[1], ImageFormat::from($match[2]))) {
                return;
            }
        }
        throw new BackfillSafetyException(SafetyError::InvalidInput);
    }

    public function validateBytes(string $bytes): void
    {
        if (strlen($bytes) !== $this->size || ! hash_equals($this->sha256, hash('sha256', $bytes))) {
            throw new BackfillSafetyException(SafetyError::InvalidInput);
        }
        try {
            if ($this->kind === ObjectKind::Manifest) {
                $data = json_decode(JournalPayload::manifest($bytes), true, 16, JSON_THROW_ON_ERROR);
                ResponsiveManifest::fromJson($bytes, $data['master']['key'] ?? '', $this->key,
                    new ResponsiveMediaKeys(new MediaObjectKeyGenerator));
            } else {
                $info = @getimagesizefromstring($bytes);
                preg_match('/\/w([0-9]+)\./', $this->key, $match);
                if (! $info || ($info['mime'] ?? '') !== $this->mimeType || $info[0] !== (int) $match[1]) {
                    throw new BackfillSafetyException(SafetyError::InvalidInput);
                }
            }
        } catch (Throwable) {
            throw new BackfillSafetyException(SafetyError::InvalidInput);
        }
    }
}
