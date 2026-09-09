<?php

namespace App\Services\Media\Backfill\Safety;

use App\Services\Media\MediaObjectKeyGenerator;
use App\Services\Media\ResponsiveManifest;

final class JournalPayload
{
    public static function sha256(string $value): string
    {
        if (preg_match('/\A[0-9a-f]{64}\z/', $value) !== 1) {
            throw new BackfillSafetyException(SafetyError::InvalidInput);
        }

        return $value;
    }

    public static function manifest(string $json): string
    {
        if (strlen($json) > ResponsiveManifest::MAX_BYTES) {
            throw new BackfillSafetyException(SafetyError::InvalidInput);
        }
        try {
            json_decode($json, true, 16, JSON_THROW_ON_ERROR);
        } catch (\Throwable) {
            throw new BackfillSafetyException(SafetyError::InvalidInput);
        }

        return $json;
    }

    public static function reference(mixed $input): array
    {
        if (is_string($input)) {
            $hash = hash('sha256', $input);
            if ((new MediaObjectKeyGenerator)->isValid($input)) {
                return ['master_key' => $input, 'master_key_hash' => $hash, 'reference_sample' => null];
            }
        } else {
            // Do not invoke object serializers or traverse arbitrary/cyclic arrays.
            $hash = hash('sha256', 'invalid-type:'.get_debug_type($input));
        }

        // Type-only sample deliberately discloses neither URL credentials nor arbitrary input.
        return ['master_key' => null, 'master_key_hash' => $hash,
            'reference_sample' => is_string($input) ? '[invalid string; bytes='.strlen($input).']' : '[invalid '.gettype($input).']'];
    }

    public static function receipt(?string $value, int $limit): ?string
    {
        if ($value === null) {
            return null;
        }
        if (strlen($value) > $limit || preg_match('/[^\x20-\x7e]/', $value)) {
            throw new BackfillSafetyException(SafetyError::InvalidInput);
        }

        return $value;
    }
}
