<?php

namespace App\Services\Media;

use App\Services\Media\Exceptions\InvalidMediaImage;

enum ImageFormat: string
{
    case Jpeg = 'jpg';
    case Png = 'png';
    case Webp = 'webp';

    public function mimeType(): string
    {
        return match ($this) {
            self::Jpeg => 'image/jpeg',
            self::Png => 'image/png',
            self::Webp => 'image/webp',
        };
    }

    public static function fromMimeType(string $mimeType): self
    {
        foreach (self::cases() as $format) {
            if ($format->mimeType() === $mimeType) {
                return $format;
            }
        }

        throw new InvalidMediaImage('El formato de imagen no está permitido.');
    }
}
