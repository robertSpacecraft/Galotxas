<?php

namespace App\Services\Media\Backfill\Reconciliation;

/**
 * One planned object as D2-C observed it. Keys, expected hashes and expected sizes are immutable
 * journal facts and are deliberately not duplicated here: the repository compares the observation
 * against the durable plan.
 */
final readonly class ForwardObjectEvidence
{
    public function __construct(
        public int $objectId,
        public string $observedSha256,
        public int $observedSize,
        public string $observedMimeType,
        public bool $descriptorValidated,
        public bool $structureValidated,
    ) {
        if ($objectId < 1 || $observedSize < 1
            || preg_match('/\A[0-9a-f]{64}\z/D', $observedSha256) !== 1
            || preg_match('/\A[a-z]+\/[a-z0-9.+-]+\z/D', $observedMimeType) !== 1) {
            throw new ReconciliationException(ReconciliationError::InvalidInput);
        }
    }

    /** @return array<string, bool|int|string> */
    public function canonical(): array
    {
        return [
            'object_id' => $this->objectId,
            'observed_sha256' => $this->observedSha256,
            'observed_size' => $this->observedSize,
            'observed_mime_type' => $this->observedMimeType,
            'descriptor_validated' => $this->descriptorValidated,
            'structure_validated' => $this->structureValidated,
        ];
    }
}
