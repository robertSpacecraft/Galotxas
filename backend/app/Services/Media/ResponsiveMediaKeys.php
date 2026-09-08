<?php

namespace App\Services\Media;

use InvalidArgumentException;

class ResponsiveMediaKeys
{
    public function __construct(private readonly MediaObjectKeyGenerator $masters) {}

    public function manifest(string $masterKey, VariantPolicyVersion $version): string
    {
        return $this->prefix($masterKey, $version).'/manifest.json';
    }

    public function variant(
        string $masterKey,
        ResponsiveImageProfile $profile,
        VariantPolicyVersion $version,
        int $width,
        ImageFormat $format,
    ): string {
        if (! $this->masters->isValidForPurpose($masterKey, $profile->purpose())
            || ! in_array($width, $version->widths($profile), true)
            || ! in_array($format, [ImageFormat::Png, ImageFormat::Webp], true)) {
            throw new InvalidArgumentException('La clave de variante no es válida.');
        }

        return $this->prefix($masterKey, $version).'/w'.$width.'.'.$format->value;
    }

    public function isValidManifest(string $key, string $masterKey, VariantPolicyVersion $version): bool
    {
        return $this->masters->isValid($masterKey) && $key === $this->manifest($masterKey, $version);
    }

    public function isValidVariant(
        string $key,
        string $masterKey,
        ResponsiveImageProfile $profile,
        VariantPolicyVersion $version,
        int $width,
        ImageFormat $format,
    ): bool {
        try {
            return $key === $this->variant($masterKey, $profile, $version, $width, $format);
        } catch (InvalidArgumentException) {
            return false;
        }
    }

    private function prefix(string $masterKey, VariantPolicyVersion $version): string
    {
        if (! $this->masters->isValid($masterKey)) {
            throw new InvalidArgumentException('La clave multimedia no es válida.');
        }

        // Split only after the existing strict master parser has accepted the whole key.
        [$purpose, $filename] = explode('/', $masterKey);
        [$uuid] = explode('.', $filename);

        return 'variants/'.$version->value.'/'.$purpose.'/'.$uuid;
    }
}
