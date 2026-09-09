<?php

namespace App\Services\Media;

use App\Services\Media\Exceptions\InvalidMediaImage;

/** No new master bytes: the descriptor refers to the unchanged stored object. */
final readonly class PreparedResponsiveDerivatives
{
    public ManifestImage $master;

    public VariantPolicyVersion $version;

    public ResponsiveManifest $manifest;

    /** @param list<NormalizedImage> $variants */
    public function __construct(
        ManifestImage $master,
        public string $masterSha256,
        public array $variants,
        public ResponsiveImageProfile $profile,
        public ImagePreparationPolicy $policy,
        ResponsiveMediaKeys $keys,
    ) {
        if (! array_is_list($variants) || preg_match('/\A[0-9a-f]{64}\z/', $masterSha256) !== 1) {
            throw new InvalidMediaImage('El conjunto de derivados no es válido.');
        }
        foreach ($variants as $variant) {
            if (! $variant instanceof NormalizedImage) {
                throw new InvalidMediaImage('El derivado no es válido.');
            }
            $header = @getimagesizefromstring($variant->bytes);
            if ($header === false || strlen($variant->bytes) !== $variant->size
                || $header[0] !== $variant->width || $header[1] !== $variant->height || $header['mime'] !== $variant->mimeType) {
                throw new InvalidMediaImage('Los bytes del derivado no son válidos.');
            }
        }
        $this->manifest = ResponsiveManifest::fromPreservedMaster($master, $variants, $profile, $policy, $keys);
        $this->master = $this->manifest->master;
        $this->version = $this->manifest->version;
    }
}
