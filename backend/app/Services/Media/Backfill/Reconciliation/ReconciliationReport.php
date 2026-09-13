<?php

namespace App\Services\Media\Backfill\Reconciliation;

use App\Services\Media\Backfill\Safety\RecoveryBarrierState;
use App\Services\Media\Backfill\Safety\RunState;

/** Safe internal facts from one exact-run invocation; it never contains object keys or evidence. */
final readonly class ReconciliationReport
{
    public function __construct(
        public ReconciliationOutcome $outcome,
        public string $runId,
        public ?string $attemptId,
        public bool $noOp,
        public int $itemsTraversed,
        public int $itemsSkipped,
        public int $itemsResolvedForward,
        public int $itemsResolvedNoEffect,
        public ?ReconciliationBlockReason $firstBlocker,
        public bool $runClosureAppended,
        public ?RunState $finalRunState,
        public ?RecoveryBarrierState $globalRecoveryBarrier,
        public ?bool $durableProgressOccurred,
    ) {
        $isNoOpOutcome = in_array($outcome, [
            ReconciliationOutcome::AlreadyClosed,
            ReconciliationOutcome::NoReconciliationRequired,
        ], true);
        if (! $this->canonicalUuid($runId)
            || ($attemptId !== null && ! $this->canonicalUuid($attemptId))
            || min($itemsTraversed, $itemsSkipped, $itemsResolvedForward, $itemsResolvedNoEffect) < 0
            || $itemsSkipped + $itemsResolvedForward + $itemsResolvedNoEffect > $itemsTraversed
            || $noOp !== $isNoOpOutcome
            || ($noOp && ($attemptId !== null
                || $itemsTraversed !== 0
                || $itemsSkipped !== 0
                || $itemsResolvedForward !== 0
                || $itemsResolvedNoEffect !== 0
                || $firstBlocker !== null
                || $runClosureAppended
                || $durableProgressOccurred !== false))
            || ($runClosureAppended && $finalRunState !== RunState::Interrupted)) {
            throw new \InvalidArgumentException('El informe de reconciliación no es válido.');
        }
    }

    public function withOutcome(ReconciliationOutcome $outcome, ?bool $durableProgressOccurred = null): self
    {
        return new self(
            $outcome,
            $this->runId,
            $this->attemptId,
            false,
            $this->itemsTraversed,
            $this->itemsSkipped,
            $this->itemsResolvedForward,
            $this->itemsResolvedNoEffect,
            $this->firstBlocker,
            $this->runClosureAppended,
            $this->finalRunState,
            $this->globalRecoveryBarrier,
            $durableProgressOccurred,
        );
    }

    private function canonicalUuid(string $value): bool
    {
        return preg_match('/\A[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}\z/D', $value) === 1;
    }
}
