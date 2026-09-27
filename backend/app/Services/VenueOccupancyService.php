<?php

namespace App\Services;

use App\Enums\GameMatchStatus;
use App\Exceptions\VenueOccupancyConflictException;
use App\Models\GameMatch;
use App\Models\Venue;
use Carbon\CarbonInterface;
use Closure;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use LogicException;

class VenueOccupancyService
{
    private const OCCUPANCY_SECONDS = 3600;

    public const UNIQUE_INDEX = 'game_matches_venue_occupancy_unique';

    public const GENERATED_COLUMN = 'occupancy_guard';

    public function __construct(
        private readonly VenueOccupancyPolicy $policy,
        private readonly MatchScheduleTimePolicy $timePolicy,
    ) {}

    /**
     * @return EloquentCollection<int, Venue>
     */
    public function lockAllVenues(): EloquentCollection
    {
        $this->assertInsideTransaction();

        return Venue::query()
            ->orderBy('id')
            ->lockForUpdate()
            ->get();
    }

    /**
     * Lock every origin/destination Venue in one deterministic batch.
     *
     * @param  iterable<int|null>  $venueIds
     * @return EloquentCollection<int, Venue>
     */
    public function lockVenues(iterable $venueIds): EloquentCollection
    {
        $this->assertInsideTransaction();

        $ids = collect($venueIds)
            ->filter(static fn ($id): bool => $id !== null)
            ->map(static fn ($id): int => (int) $id)
            ->filter(static fn (int $id): bool => $id > 0)
            ->unique()
            ->sort()
            ->values();

        if ($ids->isEmpty()) {
            return new EloquentCollection;
        }

        /** @var EloquentCollection<int, Venue> $venues */
        $venues = Venue::query()
            ->whereKey($ids->all())
            ->orderBy('id')
            ->lockForUpdate()
            ->get();

        if ($venues->count() !== $ids->count()) {
            throw new InvalidArgumentException('La pista seleccionada no existe.');
        }

        return $venues;
    }

    public function assertTargetCanBePersisted(
        GameMatch $lockedMatch,
        ?CarbonInterface $scheduledAt,
        ?int $venueId,
        GameMatchStatus|string|null $status,
    ): void {
        $this->lockVenues([$lockedMatch->venue_id, $venueId]);

        $targetOccupies = $this->policy->occupies($status, $venueId, $scheduledAt);

        if (! $targetOccupies) {
            return;
        }

        $keepsCurrentOccupancy = $this->policy->occupies(
            $lockedMatch->status,
            $lockedMatch->venue_id,
            $lockedMatch->scheduled_date,
        )
            && (int) $lockedMatch->venue_id === $venueId
            && $lockedMatch->scheduled_date->equalTo($scheduledAt);

        if ($keepsCurrentOccupancy) {
            return;
        }

        $this->timePolicy->assertCanonicalStart($scheduledAt);
        $this->assertAvailable($lockedMatch->id, $scheduledAt, $venueId);
    }

    public function assertSoftAvailable(
        GameMatch $match,
        CarbonInterface $scheduledAt,
        int $venueId,
    ): void {
        $this->timePolicy->assertCanonicalStart($scheduledAt);
        $this->assertAvailable($match->id, $scheduledAt, $venueId);
    }

    /**
     * @param  list<array{venue_id: int, scheduled_at: CarbonInterface}>  $slots
     * @return list<array{venue_id: int, scheduled_at: CarbonInterface}>
     */
    public function availableSlots(array $slots): array
    {
        if ($slots === []) {
            return [];
        }

        $venueIds = collect($slots)->pluck('venue_id')->unique()->values()->all();
        $slotStarts = collect($slots)
            ->pluck('scheduled_at')
            ->sortBy(static fn (CarbonInterface $date): int => $date->getTimestamp())
            ->values();
        $windowStart = $slotStarts->first()->copy()->subHour();
        $windowEnd = $slotStarts->last()->copy()->addHour();

        $occupied = GameMatch::query()
            ->whereIn('venue_id', $venueIds)
            ->where('scheduled_date', '>', $windowStart->format('Y-m-d H:i:s'))
            ->where('scheduled_date', '<', $windowEnd->format('Y-m-d H:i:s'))
            ->whereIn('status', VenueOccupancyPolicy::OCCUPYING_STATUSES)
            ->get(['venue_id', 'scheduled_date'])
            ->groupBy('venue_id');

        return array_values(array_filter(
            $slots,
            fn (array $slot): bool => $occupied
                ->get($slot['venue_id'], collect())
                ->doesntContain(
                    fn (GameMatch $match): bool => $this->intervalsOverlap(
                        $match->scheduled_date,
                        $slot['scheduled_at']
                    )
                )
        ));
    }

    /**
     * @template T
     *
     * @param  Closure(): T  $write
     * @return T
     */
    public function withConflictTranslation(Closure $write): mixed
    {
        try {
            return $write();
        } catch (QueryException $exception) {
            throw $this->conflictFrom($exception) ?? $exception;
        }
    }

    public function conflictFrom(QueryException $exception): ?VenueOccupancyConflictException
    {
        return ($exception->errorInfo[1] ?? null) === 1062
            && str_contains($exception->getMessage(), self::UNIQUE_INDEX)
                ? new VenueOccupancyConflictException
                : null;
    }

    private function assertAvailable(
        int $excludedMatchId,
        CarbonInterface $scheduledAt,
        int $venueId,
    ): void {
        $occupied = GameMatch::query()
            ->whereKeyNot($excludedMatchId)
            ->where('venue_id', $venueId)
            ->where(
                'scheduled_date',
                '>',
                $scheduledAt->copy()->subHour()->format('Y-m-d H:i:s')
            )
            ->where(
                'scheduled_date',
                '<',
                $scheduledAt->copy()->addHour()->format('Y-m-d H:i:s')
            )
            ->whereIn('status', VenueOccupancyPolicy::OCCUPYING_STATUSES)
            ->exists();

        if ($occupied) {
            throw new VenueOccupancyConflictException;
        }
    }

    private function intervalsOverlap(
        CarbonInterface $existingStart,
        CarbonInterface $targetStart,
    ): bool {
        $existingTimestamp = $existingStart->getTimestamp();
        $targetTimestamp = $targetStart->getTimestamp();

        return $existingTimestamp < $targetTimestamp + self::OCCUPANCY_SECONDS
            && $existingTimestamp + self::OCCUPANCY_SECONDS > $targetTimestamp;
    }

    private function assertInsideTransaction(): void
    {
        if (DB::transactionLevel() < 1) {
            throw new LogicException('Los locks de ocupación de pistas requieren una transacción activa.');
        }
    }
}
