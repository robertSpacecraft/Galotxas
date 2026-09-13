<?php

namespace App\Services\Media\Backfill\Reconciliation;

/** Typed exact-key observation. Raw bytes and storage exceptions never cross this boundary. */
final readonly class ExactObjectObservation
{
    private function __construct(
        public ObjectObservation $classification,
        public ?string $observedSha256 = null,
        public ?int $observedSize = null,
        public ?string $observedMimeType = null,
        public bool $descriptorValidated = false,
        public bool $structureValidated = false,
    ) {}

    public static function absentNow(): self
    {
        return new self(ObjectObservation::AbsentNow);
    }

    public static function differentContentPresent(): self
    {
        return new self(ObjectObservation::DifferentContentPresent);
    }

    public static function unreadable(): self
    {
        return new self(ObjectObservation::Unreadable);
    }

    public static function expectedContentPresent(
        string $sha256,
        int $size,
        string $mimeType,
        bool $descriptorValidated,
        bool $structureValidated,
    ): self {
        if (preg_match('/\A[0-9a-f]{64}\z/D', $sha256) !== 1 || $size < 1
            || preg_match('/\A[a-z]+\/[a-z0-9.+-]+\z/D', $mimeType) !== 1) {
            throw new ReconciliationException(ReconciliationError::InvalidInput);
        }

        return new self(
            ObjectObservation::ExpectedContentPresent,
            $sha256,
            $size,
            $mimeType,
            $descriptorValidated,
            $structureValidated,
        );
    }
}
