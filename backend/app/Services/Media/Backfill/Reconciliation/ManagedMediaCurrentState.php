<?php

namespace App\Services\Media\Backfill\Reconciliation;

/** Validated current owner/reference/master facts. Master bytes never leave the validator. */
final readonly class ManagedMediaCurrentState
{
    public function __construct(
        public string $referenceIdentitySha256,
        public string $masterKeySha256,
        public string $masterSha256,
        public string $liveOwnerDomain,
        public int $liveOwnerEntityId,
    ) {
        foreach ([$referenceIdentitySha256, $masterKeySha256, $masterSha256] as $hash) {
            if (preg_match('/\A[0-9a-f]{64}\z/D', $hash) !== 1) {
                throw new OperationalEvidenceException(ReconciliationBlockReason::DomainRevalidationFailed);
            }
        }
        if ($liveOwnerDomain === '' || $liveOwnerEntityId < 1) {
            throw new OperationalEvidenceException(ReconciliationBlockReason::DomainRevalidationFailed);
        }
    }
}
