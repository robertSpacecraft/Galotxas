<?php

namespace App\Services\Media\Backfill\Reconciliation;

/** DB-only item analysis. Historical blockers remain visible even when an exact projection resolves them. */
final readonly class ReconciliationItemOperationalAnalysis
{
    /**
     * @param  list<int>  $ambiguousWriteObjectIds
     * @param  list<int>  $cleanupBlockerObjectIds
     */
    public function __construct(
        public ReconciliationItemOperationalState $state,
        public bool $unfinishedItemBlocker,
        public array $ambiguousWriteObjectIds,
        public array $cleanupBlockerObjectIds,
        public bool $noEffectEligible,
        public bool $forwardEvidenceRequired,
    ) {}

    public static function inconsistent(): self
    {
        return new self(ReconciliationItemOperationalState::Inconsistent, false, [], [], false, false);
    }

    public function hasCleanupAttention(): bool
    {
        return $this->cleanupBlockerObjectIds !== [];
    }
}
