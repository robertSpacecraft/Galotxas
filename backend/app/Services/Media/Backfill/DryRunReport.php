<?php

namespace App\Services\Media\Backfill;

final readonly class DryRunReport
{
    /**
     * @param  list<ManagedMediaDomain>  $domains
     * @param  array<string, int>  $upperBounds
     * @param  array<string, int>  $classificationCounts
     * @param  list<DryRunAnomaly>  $anomalies
     */
    public function __construct(
        public array $domains,
        public array $upperBounds,
        public int $observedCount,
        public array $classificationCounts,
        public int $candidateCount,
        public int $blockerCount,
        public array $anomalies,
        public int $omittedAnomalyCount,
        public bool $truncated,
        public ?ManagedMediaDomain $lastObservedDomain,
        public ?int $lastObservedId,
    ) {}

    public function hasBlockers(): bool
    {
        return $this->blockerCount > 0;
    }
}
