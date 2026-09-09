<?php

namespace App\Services\Media;

use App\Services\Media\Exceptions\InvalidMediaImage;
use Intervention\Image\Interfaces\ImageInterface;

class ImageDerivativeEncoder
{
    public function encode(ImageInterface $image, ImagePreparationPolicy $policy): NormalizedImage
    {
        if ($policy === ImagePreparationPolicy::Photo) {
            return $this->encoded($image, (string) $image->toWebp(quality: 82, strip: true), ImageFormat::Webp);
        }

        // Intervention 3 GD maps quality 100 to IMG_WEBP_LOSSLESS, not lossy quality 100.
        if (! defined('IMG_WEBP_LOSSLESS')) {
            throw new InvalidMediaImage('La codificación sin pérdida no está disponible.');
        }

        $png = $this->encoded($image, (string) $image->toPng(interlaced: false, indexed: false), ImageFormat::Png);
        $webp = $this->encoded($image, (string) $image->toWebp(quality: 100, strip: true), ImageFormat::Webp);

        return $webp->size < $png->size ? $webp : $png;
    }

    private function encoded(ImageInterface $image, string $bytes, ImageFormat $format): NormalizedImage
    {
        $header = @getimagesizefromstring($bytes);
        if ($header === false || $header[0] !== $image->width() || $header[1] !== $image->height()
            || $header['mime'] !== $format->mimeType()) {
            throw new InvalidMediaImage('La normalización de la imagen no produjo contenido válido.');
        }

        return new NormalizedImage($bytes, $format->mimeType(), $format->value, $image->width(), $image->height(), strlen($bytes));
    }
}
