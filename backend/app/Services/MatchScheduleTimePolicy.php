<?php

namespace App\Services;

use Carbon\Carbon;
use Carbon\CarbonInterface;
use InvalidArgumentException;

class MatchScheduleTimePolicy
{
    public const NON_CANONICAL_MESSAGE = 'Los partidos que ocupan pista deben comenzar a una hora exacta (HH:00).';

    public function fromHumanInput(string $date, string $time): Carbon
    {
        return Carbon::createFromFormat('Y-m-d H:i', $date.' '.$time)
            ->setSecond(0)
            ->setMicrosecond(0);
    }

    public function assertCanonicalStart(CarbonInterface $scheduledAt): void
    {
        if (
            (int) $scheduledAt->format('i') !== 0
            || (int) $scheduledAt->format('s') !== 0
            || (int) $scheduledAt->format('u') !== 0
        ) {
            throw new InvalidArgumentException(self::NON_CANONICAL_MESSAGE);
        }
    }
}
