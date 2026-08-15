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
        $profile = $this->profile($profileName);

        if (! $file->isValid()) {
            throw new InvalidMediaImage('El archivo de imagen no es una subida válida.');
        }

        $size = $file->getSize();

        if ($size === false || $size > $profile['input_max_kb'] * 1024) {
            throw new InvalidMediaImage('La imagen supera el tamaño máximo permitido.');
        }

        try {
            $mimeType = $file->getMimeType();
            $bytes = file_get_contents($file->getRealPath());
        } catch (Throwable $exception) {
            throw new InvalidMediaImage('No se pudo leer la imagen.', previous: $exception);
        }

        if (! is_string($mimeType) || ! in_array($mimeType, config('media.allowed_mime_types', []), true)) {
            throw new InvalidMediaImage('El formato de imagen no está permitido.');
        }

        if ($bytes === false || $bytes === '') {
            throw new InvalidMediaImage('No se pudo leer la imagen.');
        }

        $inputSize = @getimagesizefromstring($bytes);

        if ($inputSize === false || ($inputSize['mime'] ?? null) !== $mimeType) {
            throw new InvalidMediaImage('El contenido no es una imagen válida.');
        }

        [$inputWidth, $inputHeight] = $inputSize;
        $this->assertDimensions($inputWidth, $inputHeight, $profile);

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
            extension: $this->extensionFor($mimeType),
            width: $image->width(),
            height: $image->height(),
            size: strlen($normalizedBytes),
        );
    }

    /**
     * @return array{input_max_kb: int, max_pixels: int, max_width: int, max_height: int, output_max_width: int, output_max_height: int, jpeg_quality: int, webp_quality: int}
     */
    private function profile(string $profileName): array
    {
        $profile = config("media.profiles.{$profileName}");

        if (! is_array($profile)) {
            throw new InvalidMediaImage('El perfil de imagen solicitado no existe.');
        }

        return $profile;
    }

    /**
     * @param  array{input_max_kb: int, max_pixels: int, max_width: int, max_height: int, output_max_width: int, output_max_height: int, jpeg_quality: int, webp_quality: int}  $profile
     */
    private function assertDimensions(int $width, int $height, array $profile): void
    {
        if ($width < 1 || $height < 1) {
            throw new InvalidMediaImage('Las dimensiones de la imagen no son válidas.');
        }

        if ($width > $profile['max_width'] || $height > $profile['max_height']) {
            throw new InvalidMediaImage('Las dimensiones de la imagen superan el límite permitido.');
        }

        if ($width > intdiv($profile['max_pixels'], $height)) {
            throw new InvalidMediaImage('La imagen supera el máximo de píxeles permitido.');
        }
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

    private function extensionFor(string $mimeType): string
    {
        return match ($mimeType) {
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp',
            default => throw new InvalidMediaImage('El formato de imagen no está permitido.'),
        };
    }
}
