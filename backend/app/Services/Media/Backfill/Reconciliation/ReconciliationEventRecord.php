<?php

namespace App\Services\Media\Backfill\Reconciliation;

/** Immutable outcome of one journal call. Exposes fingerprints, never raw identifiers or evidence. */
final readonly class ReconciliationEventRecord
{
    public function __construct(
        public ReconciliationEventType $type,
        public string $eventFingerprint,
        public string $attemptFingerprint,
        public int $evidenceVersion,
        public bool $replayed,
        public ?ItemReconciliationResult $itemResult,
        public int $objectsResolved,
    ) {}
}
