<?php

namespace App\Services\Media;

use Illuminate\Contracts\Cache\Factory as CacheFactory;
use Throwable;

final class ResponsiveMediaResolver
{
    public const POSITIVE_TTL_SECONDS = 600;

    public const NEGATIVE_TTL_SECONDS = 60;

    private const CACHE_MISS = ['manifest' => null];

    public function __construct(
        private readonly ResponsiveMediaStorage $storage,
        private readonly MediaObjectKeyGenerator $keys,
        private readonly CacheFactory $cache,
    ) {}

    public function manifest(mixed $masterKey, ResponsiveImageProfile $profile): ?ResponsiveManifest
    {
        if (! is_string($masterKey) || ! $this->keys->isValidForPurpose($masterKey, $profile->purpose())) {
            return null;
        }

        $cacheKey = $this->cacheKey($masterKey, $profile);
        $store = null;

        try {
            $store = $this->cache->store();
            $cached = $store->get($cacheKey);
            if ($cached === self::CACHE_MISS) {
                return null;
            }
            if ($cached instanceof ResponsiveManifest && $cached->profile === $profile) {
                return $cached;
            }
        } catch (Throwable) {
            // Cache outages must not prevent the master or a valid manifest from resolving.
        }

        $manifest = $this->storage->readManifest($masterKey);
        if ($manifest?->profile !== $profile) {
            $manifest = null;
        }

        if ($store !== null) {
            try {
                $store->put(
                    $cacheKey,
                    $manifest ?? self::CACHE_MISS,
                    $manifest === null ? self::NEGATIVE_TTL_SECONDS : self::POSITIVE_TTL_SECONDS,
                );
            } catch (Throwable) {
                // Resolution remains available when the bounded cache cannot be written.
            }
        }

        return $manifest;
    }

    /**
     * @param  callable(int): string  $variantUrl
     * @return array<string, mixed>
     */
    public function image(
        mixed $masterKey,
        ResponsiveImageProfile $profile,
        string $masterUrl,
        callable $variantUrl,
        mixed $width = null,
        mixed $height = null,
    ): array {
        $manifest = $this->manifest($masterKey, $profile);
        $image = ['url' => $masterUrl];

        $resolvedWidth = is_int($width) && $width > 0 ? $width : $manifest?->master->width;
        $resolvedHeight = is_int($height) && $height > 0 ? $height : $manifest?->master->height;
        if ($resolvedWidth !== null) {
            $image['width'] = $resolvedWidth;
        }
        if ($resolvedHeight !== null) {
            $image['height'] = $resolvedHeight;
        }

        if ($manifest !== null && $manifest->variants !== []) {
            $image['variants'] = array_map(static fn (ManifestImage $variant): array => [
                'url' => $variantUrl($variant->width),
                'width' => $variant->width,
                'height' => $variant->height,
                'mime_type' => $variant->mimeType,
            ], $manifest->variants);
        }

        return $image;
    }

    public function variant(mixed $masterKey, ResponsiveImageProfile $profile, int $width): ?ManifestImage
    {
        $manifest = $this->manifest($masterKey, $profile);
        if ($manifest === null) {
            return null;
        }

        foreach ($manifest->variants as $variant) {
            if ($variant->width === $width) {
                return $variant;
            }
        }

        return null;
    }

    private function cacheKey(string $masterKey, ResponsiveImageProfile $profile): string
    {
        return 'responsive-manifest:v1:'.$profile->value.':'.hash('sha256', $masterKey);
    }
}
