<?php

namespace App\Services\Media;

use App\Services\Media\Exceptions\InvalidMediaImage;
use Illuminate\Http\UploadedFile;
use Intervention\Image\ImageManager;
use Intervention\Image\Interfaces\ImageInterface;
use Intervention\Image\Interfaces\ImageManagerInterface;
use Throwable;

class ResponsiveImagePreparer
{
    private readonly ImageManagerInterface $manager;

    public function __construct(?ImageManagerInterface $manager = null)
    {
        $this->manager = $manager ?? ImageManager::gd(
            autoOrientation: true,
            decodeAnimation: false,
            strip: true,
        );
    }

    public function prepare(
        UploadedFile $file,
        ResponsiveImageProfile $profile,
        ImagePreparationPolicy $policy,
    ): PreparedResponsiveSet {
        $version = VariantPolicyVersion::V1;
        $widths = $version->widths($profile);
        $validator = new ImageInputValidator;
        $limits = $validator->profile($profile->value);
        $input = $validator->validate($file, $limits);

        try {
            // One source decode, including orientation. Every candidate uses these pixels.
            $source = $this->manager->read($input['bytes']);
            $masterImage = clone $source;
            $masterImage->scaleDown(width: $limits['output_max_width'], height: $limits['output_max_height']);
            $master = $this->encode($masterImage, $policy);
            unset($masterImage);

            $variants = [];
            foreach ($widths as $width) {
                if ($width >= $master->width) {
                    continue;
                }
                $candidate = clone $source;
                $candidate->scaleDown(width: $width);
                $variants[] = $this->encode($candidate, $policy);
                unset($candidate);
            }

            return new PreparedResponsiveSet($master, $variants, $profile, $policy, $version);
        } catch (Throwable $exception) {
            throw new InvalidMediaImage('La imagen no se pudo decodificar y normalizar.', previous: $exception);
        }
    }

    private function encode(ImageInterface $image, ImagePreparationPolicy $policy): NormalizedImage
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
