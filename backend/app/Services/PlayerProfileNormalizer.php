<?php

namespace App\Services;

class PlayerProfileNormalizer
{
    public function __construct(private readonly UnicodeTextService $text) {}

    public function nickname(mixed $value): mixed
    {
        if ($value !== null && ! is_string($value)) {
            return $value;
        }

        $normalized = $this->text->squish($value);

        return $normalized === '' ? null : $normalized;
    }

    public function licenseNumber(mixed $value): mixed
    {
        if ($value !== null && ! is_string($value)) {
            return $value;
        }

        $normalized = $this->text->trim($value);

        return $normalized === '' ? null : $normalized;
    }

    public function optionalText(mixed $value): mixed
    {
        if ($value !== null && ! is_string($value)) {
            return $value;
        }

        $normalized = $this->text->trim($value);

        return $normalized === '' ? null : $normalized;
    }
}
