<?php

namespace App\Services;

use App\Enums\GameMatchStatus;
use Carbon\CarbonInterface;

class VenueOccupancyPolicy
{
    /** @var list<string> */
    public const OCCUPYING_STATUSES = [
        GameMatchStatus::SCHEDULED->value,
        GameMatchStatus::SUBMITTED->value,
        GameMatchStatus::UNDER_REVIEW->value,
        GameMatchStatus::VALIDATED->value,
    ];

    public function isOccupyingStatus(GameMatchStatus|string|null $status): bool
    {
        $value = $status instanceof GameMatchStatus ? $status->value : $status;

        return is_string($value) && in_array($value, self::OCCUPYING_STATUSES, true);
    }

    public function occupies(
        GameMatchStatus|string|null $status,
        ?int $venueId,
        ?CarbonInterface $scheduledAt,
    ): bool {
        return $venueId !== null
            && $scheduledAt !== null
            && $this->isOccupyingStatus($status);
    }
}
