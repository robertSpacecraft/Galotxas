<?php

namespace App\Services\Media\Backfill\Reconciliation;

use DateTimeInterface;

/**
 * Evidence version 1 of a non-destructive forward acceptance. D2-C observes and revalidates
 * domain, reference, master and every planned object, then hands this immutable snapshot to the
 * journal, which validates it against durable facts without any storage I/O of its own.
 */
final readonly class ForwardItemEvidence
{
    /** @param list<ForwardObjectEvidence> $objects */
    public function __construct(
        public DateTimeInterface $observedAt,
        public string $storageIdentityHash,
        public ReconciliationBackendMode $backendMode,
        public string $referenceIdentitySha256,
        public string $masterKeySha256,
        public string $masterSha256,
        public string $candidateManifestSha256,
        public bool $uniqueLiveOwnerRevalidated,
        public string $liveOwnerDomain,
        public int $liveOwnerEntityId,
        public bool $manifestStructureValidated,
        public bool $noUnexpectedCanonicalTarget,
        public array $objects,
    ) {
        foreach ([$storageIdentityHash, $referenceIdentitySha256, $masterKeySha256, $masterSha256,
            $candidateManifestSha256] as $hash) {
            if (preg_match('/\A[0-9a-f]{64}\z/D', $hash) !== 1) {
                throw new ReconciliationException(ReconciliationError::InvalidInput);
            }
        }
        if ($liveOwnerEntityId < 1 || $liveOwnerDomain === '' || $objects === []
            || array_keys($objects) !== range(0, count($objects) - 1)) {
            throw new ReconciliationException(ReconciliationError::InvalidInput);
        }
        $previous = 0;
        foreach ($objects as $object) {
            if (! $object instanceof ForwardObjectEvidence || $object->objectId <= $previous) {
                throw new ReconciliationException(ReconciliationError::InvalidInput);
            }
            $previous = $object->objectId;
        }
    }

    /** @return list<int> */
    public function objectIds(): array
    {
        return array_map(static fn (ForwardObjectEvidence $object): int => $object->objectId, $this->objects);
    }

    /** Stable body; the repository prepends the shared envelope. @return array<string, mixed> */
    public function canonical(): array
    {
        return [
            'reference_identity_sha256' => $this->referenceIdentitySha256,
            'master_key_sha256' => $this->masterKeySha256,
            'master_sha256' => $this->masterSha256,
            'candidate_manifest_sha256' => $this->candidateManifestSha256,
            'unique_live_owner_revalidated' => $this->uniqueLiveOwnerRevalidated,
            'live_owner_domain' => $this->liveOwnerDomain,
            'live_owner_entity_id' => $this->liveOwnerEntityId,
            'manifest_structure_validated' => $this->manifestStructureValidated,
            'no_unexpected_canonical_target' => $this->noUnexpectedCanonicalTarget,
            'objects' => array_map(
                static fn (ForwardObjectEvidence $object): array => $object->canonical(),
                $this->objects,
            ),
        ];
    }
}
