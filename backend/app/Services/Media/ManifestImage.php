<?php

namespace App\Services\Media;

use InvalidArgumentException;
use stdClass;

/** Internal storage descriptor; never serialize directly into a public Resource. */
final readonly class ManifestImage
{
    private function __construct(
        public string $key,
        public int $width,
        public int $height,
        public string $mimeType,
        public int $size,
    ) {}

    public static function fromObject(mixed $data): self
    {
        if (! $data instanceof stdClass) {
            throw new InvalidArgumentException('El descriptor de imagen no es válido.');
        }
        $fields = get_object_vars($data);
        $expected = ['key', 'width', 'height', 'mime_type', 'size'];
        if (count($fields) !== count($expected) || array_diff($expected, array_keys($fields)) !== []
            || ! is_string($data->key) || ! is_string($data->mime_type)) {
            throw new InvalidArgumentException('El descriptor de imagen no es válido.');
        }
        foreach (['width', 'height', 'size'] as $field) {
            if (! is_int($data->$field) || $data->$field < 1) {
                throw new InvalidArgumentException('El descriptor de imagen no es válido.');
            }
        }

        $format = ImageFormat::fromMimeType($data->mime_type);
        if (! str_ends_with($data->key, '.'.$format->value)) {
            throw new InvalidArgumentException('El formato del descriptor no es válido.');
        }

        return new self($data->key, $data->width, $data->height, $data->mime_type, $data->size);
    }

    public static function fromImage(string $key, NormalizedImage $image): self
    {
        return self::fromObject((object) [
            'key' => $key, 'width' => $image->width, 'height' => $image->height,
            'mime_type' => $image->mimeType, 'size' => $image->size,
        ]);
    }

    public function toArray(): array
    {
        return [
            'key' => $this->key, 'width' => $this->width, 'height' => $this->height,
            'mime_type' => $this->mimeType, 'size' => $this->size,
        ];
    }
}
