<?php

namespace App\Services;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

class CompetitionScheduleDateTimeFormatter
{
    public static function format(?CarbonInterface $value): ?string
    {
        // Stored schedule components are Madrid civil time, not a UTC instant.
        // Shift an immutable copy so serialization never changes the model.
        return $value === null
            ? null
            : CarbonImmutable::instance($value)
                ->shiftTimezone('Europe/Madrid')
                ->toIso8601String();
    }
}
