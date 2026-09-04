<?php

namespace App\Services;

use App\Exceptions\InvalidReopenReasonException;

class OfficialResultReopenReasonService
{
    public function __construct(
        private readonly UnicodeTextService $text,
    ) {}

    public function normalize(string $reason): string
    {
        $normalized = $this->text->normalizeMultiline($reason);

        if ($normalized === '' || mb_strlen($normalized) > 2000) {
            throw new InvalidReopenReasonException;
        }

        return $normalized;
    }
}
