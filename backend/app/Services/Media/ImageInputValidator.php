<?php

namespace App\Services\Media;

use App\Services\Media\Exceptions\InvalidMediaImage;
use Illuminate\Http\UploadedFile;
use Throwable;

class ImageInputValidator
{
    public function profile(string $name): array
    {
        $profile = config("media.profiles.{$name}");

        if (! is_array($profile)) {
            throw new InvalidMediaImage('El perfil de imagen solicitado no existe.');
        }

        return $profile;
    }

    /** Header checks deliberately precede the expensive pixel decode. */
    public function validate(UploadedFile $file, array $profile): array
    {
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

        [$width, $height] = $inputSize;

        if ($width < 1 || $height < 1) {
            throw new InvalidMediaImage('Las dimensiones de la imagen no son válidas.');
        }

        if ($width > $profile['max_width'] || $height > $profile['max_height']) {
            throw new InvalidMediaImage('Las dimensiones de la imagen superan el límite permitido.');
        }

        if ($width > intdiv($profile['max_pixels'], $height)) {
            throw new InvalidMediaImage('La imagen supera el máximo de píxeles permitido.');
        }

        return ['bytes' => $bytes, 'mime_type' => $mimeType];
    }
}
