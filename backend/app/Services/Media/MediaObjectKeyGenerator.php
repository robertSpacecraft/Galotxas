<?php

namespace App\Services\Media;

use Illuminate\Support\Str;
use InvalidArgumentException;

class MediaObjectKeyGenerator
{
    private const ALLOWED_EXTENSIONS = ['jpg', 'png', 'webp'];

    public function generate(MediaPurpose $purpose, string $extension): string
    {
        $extension = strtolower($extension);

        if (! in_array($extension, self::ALLOWED_EXTENSIONS, true)) {
            throw new InvalidArgumentException('La extensión multimedia no está permitida.');
        }

        return sprintf('%s/%s.%s', $purpose->value, Str::uuid(), $extension);
    }

    public function isValid(string $key): bool
    {
        return preg_match(
            '/\A(?:avatars|banners|sponsors|news|cms)\/[0-9a-f]{8}-[0-9a-f]{4}-[1-5][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}\.(?:jpg|png|webp)\z/',
            $key,
        ) === 1;
    }

    public function isValidForPurpose(string $key, MediaPurpose $purpose): bool
    {
        return str_starts_with($key, $purpose->value.'/') && $this->isValid($key);
    }
}
