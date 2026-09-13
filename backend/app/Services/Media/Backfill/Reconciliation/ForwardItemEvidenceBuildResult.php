<?php

namespace App\Services\Media\Backfill\Reconciliation;

/** Read-only result for C2: exact evidence or one of the existing five durable block reasons. */
final readonly class ForwardItemEvidenceBuildResult
{
    private function __construct(
        public ?ForwardItemEvidence $evidence,
        public ?ReconciliationBlockReason $refusal,
        public ?ReconciliationItemOperationalAnalysis $analysis,
    ) {}

    public static function accepted(
        ForwardItemEvidence $evidence,
        ReconciliationItemOperationalAnalysis $analysis,
    ): self {
        return new self($evidence, null, $analysis);
    }

    public static function refused(
        ReconciliationBlockReason $reason,
        ?ReconciliationItemOperationalAnalysis $analysis = null,
    ): self {
        return new self(null, $reason, $analysis);
    }

    public function isAccepted(): bool
    {
        return $this->evidence !== null;
    }
}
