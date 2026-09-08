<?php

namespace App\Services\Media;

use App\Services\Media\Exceptions\InvalidMediaImage;
use Illuminate\Http\UploadedFile;
use Intervention\Image\ImageManager;
use Intervention\Image\Interfaces\EncodedImageInterface;
use Intervention\Image\Interfaces\ImageInterface;
use Intervention\Image\Interfaces\ImageManagerInterface;
use Throwable;

class ImageNormalizer
{
    private ImageManagerInterface $manager;

    public function __construct(?ImageManagerInterface $manager = null)
    {
        $this->manager = $manager ?? ImageManager::gd(
            autoOrientation: true,
            decodeAnimation: false,
            strip: true,
        );
    }

    public function normalize(UploadedFile $file, string $profileName): NormalizedImage
    {
        $validator = new ImageInputValidator;
        $profile = $validator->profile($profileName);
        ['bytes' => $bytes, 'mime_type' => $mimeType] = $validator->validate($file, $profile);

        try {
            $image = $this->manager->read($bytes);
            $image->scaleDown(
                width: $profile['output_max_width'],
                height: $profile['output_max_height'],
            );
            $encoded = $this->encode($image, $mimeType, $profile);
            $normalizedBytes = (string) $encoded;
        } catch (Throwable $exception) {
            throw new InvalidMediaImage('La imagen no se pudo decodificar y normalizar.', previous: $exception);
        }

        if ($normalizedBytes === '') {
            throw new InvalidMediaImage('La normalización de la imagen no produjo contenido válido.');
        }

        return new NormalizedImage(
            bytes: $normalizedBytes,
            mimeType: $mimeType,
            extension: ImageFormat::fromMimeType($mimeType)->value,
            width: $image->width(),
            height: $image->height(),
            size: strlen($normalizedBytes),
        );
    }

    /**
     * @param  array{input_max_kb: int, max_pixels: int, max_width: int, max_height: int, output_max_width: int, output_max_height: int, jpeg_quality: int, webp_quality: int}  $profile
     */
    private function encode(ImageInterface $image, string $mimeType, array $profile): EncodedImageInterface
    {
        return match ($mimeType) {
            'image/jpeg' => $image->toJpeg(
                quality: $profile['jpeg_quality'],
                progressive: true,
                strip: true,
            ),
            'image/png' => $image->toPng(interlaced: false, indexed: false),
            'image/webp' => $image->toWebp(quality: $profile['webp_quality'], strip: true),
            default => throw new InvalidMediaImage('El formato de imagen no está permitido.'),
        };
    }
}
