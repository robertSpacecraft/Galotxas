<?php

namespace App\Services;

use Carbon\CarbonInterface;
use InvalidArgumentException;

class SchoolEnrollmentAgeService
{
    public static function isMinor(
        CarbonInterface $birthDate,
        CarbonInterface $referenceDate
    ): bool {
        $birthDay = $birthDate->toImmutable()->startOfDay();
        $referenceDay = $referenceDate->toImmutable()->startOfDay();

        if ($birthDay->isAfter($referenceDay)) {
            throw new InvalidArgumentException(
                'La fecha de nacimiento no puede ser posterior a la fecha de referencia.'
            );
        }

        return $birthDay->addYearsNoOverflow(18)->isAfter($referenceDay);
    }
}
