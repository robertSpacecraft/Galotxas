<?php

namespace App\Services\Media;

use App\Services\Media\Exceptions\InvalidMediaImage;

final readonly class PreparedResponsiveSet
{
    /** @param list<NormalizedImage> $variants Actual width is also the intended key width. */
    public function __construct(
        public NormalizedImage $master,
        public array $variants,
        public ResponsiveImageProfile $profile,
        public ImagePreparationPolicy $policy,
        public VariantPolicyVersion $version,
    ) {
        if (! array_is_list($variants)) {
            throw new InvalidMediaImage('El conjunto de imágenes no es válido.');
        }

        $previous = 0;
        foreach ($variants as $variant) {
            if (! $variant instanceof NormalizedImage
                || ! in_array($variant->width, $version->widths($profile), true)
                || $variant->width <= $previous || $variant->width >= $master->width) {
                throw new InvalidMediaImage('Las variantes de imagen no son válidas.');
            }
            $previous = $variant->width;
        }

        foreach ([$master, ...$variants] as $image) {
            $header = @getimagesizefromstring($image->bytes);
            $format = ImageFormat::tryFrom($image->extension);
            if ($header === false || $image->size <= 0 || strlen($image->bytes) !== $image->size
                || $image->width < 1 || $image->height < 1
                || $header[0] !== $image->width || $header[1] !== $image->height
                || $header['mime'] !== $image->mimeType || $format?->mimeType() !== $image->mimeType
                || $format === ImageFormat::Jpeg
                || ($policy === ImagePreparationPolicy::Photo && $format !== ImageFormat::Webp)) {
                throw new InvalidMediaImage('La imagen preparada no es válida.');
            }
        }
    }
}
