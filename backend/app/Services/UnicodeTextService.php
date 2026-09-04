<?php

namespace App\Services;

use Normalizer;

class UnicodeTextService
{
    public function normalizeNfc(?string $value): string
    {
        $value = (string) $value;

        if (class_exists(Normalizer::class)) {
            $normalized = Normalizer::normalize($value, Normalizer::FORM_C);

            if ($normalized !== false) {
                return $normalized;
            }
        }

        return $value;
    }

    public function trim(?string $value): string
    {
        return preg_replace('/^\s+|\s+$/u', '', $this->normalizeNfc($value)) ?? '';
    }

    public function squish(?string $value): string
    {
        return preg_replace('/\s+/u', ' ', $this->trim($value)) ?? '';
    }

    public function normalizeMultiline(?string $value): string
    {
        return $this->trim(str_replace(["\r\n", "\r"], "\n", (string) $value));
    }
}
