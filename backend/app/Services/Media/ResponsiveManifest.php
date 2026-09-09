<?php

namespace App\Services\Media;

use InvalidArgumentException;
use stdClass;

/** Validated private manifest. Its constructor is inaccessible to unvalidated data. */
final readonly class ResponsiveManifest
{
    public const MAX_BYTES = 16_384;

    /** @param list<ManifestImage> $variants */
    private function __construct(
        public int $schemaVersion,
        public VariantPolicyVersion $version,
        public ResponsiveImageProfile $profile,
        public ImagePreparationPolicy $policy,
        public MediaPurpose $purpose,
        public ManifestImage $master,
        public array $variants,
    ) {}

    public static function fromPrepared(string $masterKey, PreparedResponsiveSet $set, ResponsiveMediaKeys $keys): self
    {
        $variants = [];
        foreach ($set->variants as $image) {
            $key = $keys->variant($masterKey, $set->profile, $set->version, $image->width, ImageFormat::from($image->extension));
            $variants[] = ManifestImage::fromImage($key, $image)->toArray();
        }
        $json = json_encode([
            'schema_version' => 1,
            'policy_version' => $set->version->value,
            'profile' => $set->profile->value,
            'preparation_policy' => $set->policy->value,
            'purpose' => $set->profile->purpose()->value,
            'master' => ManifestImage::fromImage($masterKey, $set->master)->toArray(),
            'variants' => $variants,
        ], JSON_THROW_ON_ERROR);

        return self::fromJson($json, $masterKey, $keys->manifest($masterKey, $set->version), $keys);
    }

    public static function fromJson(string $json, string $masterKey, string $manifestKey, ResponsiveMediaKeys $keys): self
    {
        if (strlen($json) > self::MAX_BYTES) {
            throw new InvalidArgumentException('El manifiesto de imagen no es válido.');
        }
        $data = json_decode($json, false, 16, JSON_THROW_ON_ERROR);
        $expected = ['schema_version', 'policy_version', 'profile', 'preparation_policy', 'purpose', 'master', 'variants'];
        if ($data instanceof stdClass && ($data->schema_version ?? null) === 2) {
            $expected[] = 'master_mode';
            if (($data->master_mode ?? null) !== 'preserved') {
                throw new InvalidArgumentException('El modo de master no es válido.');
            }
        }
        if (! $data instanceof stdClass || count(get_object_vars($data)) !== count($expected)
            || array_diff($expected, array_keys(get_object_vars($data))) !== []) {
            throw new InvalidArgumentException('El manifiesto de imagen no es válido.');
        }
        foreach (['policy_version', 'profile', 'preparation_policy', 'purpose'] as $field) {
            if (! is_string($data->$field)) {
                throw new InvalidArgumentException('El manifiesto de imagen no es válido.');
            }
        }
        $version = VariantPolicyVersion::from($data->policy_version);
        $profile = ResponsiveImageProfile::from($data->profile);
        $policy = ImagePreparationPolicy::from($data->preparation_policy);
        $purpose = MediaPurpose::from($data->purpose);
        $master = ManifestImage::fromObject($data->master);
        $masters = new MediaObjectKeyGenerator;
        if (! in_array($data->schema_version, [1, 2], true) || $purpose !== $profile->purpose()
            || $master->key !== $masterKey || ! $masters->isValidForPurpose($masterKey, $purpose)
            || ! $keys->isValidManifest($manifestKey, $masterKey, $version)
            || ! is_array($data->variants) || ! array_is_list($data->variants)
            || count($data->variants) > count($version->widths($profile))) {
            throw new InvalidArgumentException('La identidad del manifiesto no es válida.');
        }

        $limits = (new ImageInputValidator)->profile($profile->value);
        if ($master->width > $limits['output_max_width'] || $master->height > $limits['output_max_height']) {
            throw new InvalidArgumentException('Las dimensiones del manifiesto no son válidas.');
        }

        $variants = [];
        $previous = 0;
        foreach ($data->variants as $item) {
            $variant = ManifestImage::fromObject($item);
            if ($variant->width <= $previous || $variant->width >= $master->width
                || $variant->height > $master->height
                // Both axes round independently; compare overlapping half-pixel ratio intervals.
                || abs($variant->height * $master->width - $master->height * $variant->width)
                    > ($variant->height + $variant->width + $master->height + $master->width) / 2
                || ! $keys->isValidVariant($variant->key, $masterKey, $profile, $version,
                    $variant->width, ImageFormat::fromMimeType($variant->mimeType))) {
                throw new InvalidArgumentException('La variante del manifiesto no es válida.');
            }
            $previous = $variant->width;
            $variants[] = $variant;
        }
        if ($data->schema_version === 2 && array_column($variants, 'width') !== array_values(array_filter(
            $version->widths($profile), static fn (int $width): bool => $width < $master->width,
        ))) {
            throw new InvalidArgumentException('El conjunto de variantes no está completo.');
        }
        foreach ($data->schema_version === 1 ? [$master, ...$variants] : $variants as $image) {
            $format = ImageFormat::fromMimeType($image->mimeType);
            if ($format === ImageFormat::Jpeg || ($policy === ImagePreparationPolicy::Photo && $format !== ImageFormat::Webp)) {
                throw new InvalidArgumentException('La codificación del manifiesto no es válida.');
            }
        }

        return new self($data->schema_version, $version, $profile, $policy, $purpose, $master, $variants);
    }

    /** @param list<NormalizedImage> $variants */
    public static function fromPreservedMaster(
        ManifestImage $master,
        array $variants,
        ResponsiveImageProfile $profile,
        ImagePreparationPolicy $policy,
        ResponsiveMediaKeys $keys,
    ): self {
        $version = VariantPolicyVersion::V1;
        $descriptors = [];
        foreach ($variants as $variant) {
            $key = $keys->variant($master->key, $profile, $version, $variant->width, ImageFormat::from($variant->extension));
            $descriptors[] = ManifestImage::fromImage($key, $variant)->toArray();
        }

        return self::fromJson(json_encode([
            'schema_version' => 2,
            'policy_version' => $version->value,
            'profile' => $profile->value,
            'preparation_policy' => $policy->value,
            'purpose' => $profile->purpose()->value,
            'master_mode' => 'preserved',
            'master' => $master->toArray(),
            'variants' => $descriptors,
        ], JSON_THROW_ON_ERROR), $master->key, $keys->manifest($master->key, $version), $keys);
    }

    public function toJson(): string
    {
        return json_encode([
            'schema_version' => $this->schemaVersion,
            'policy_version' => $this->version->value,
            'profile' => $this->profile->value,
            'preparation_policy' => $this->policy->value,
            'purpose' => $this->purpose->value,
            ...($this->schemaVersion === 2 ? ['master_mode' => 'preserved'] : []),
            'master' => $this->master->toArray(),
            'variants' => array_map(fn (ManifestImage $image) => $image->toArray(), $this->variants),
        ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
    }
}
