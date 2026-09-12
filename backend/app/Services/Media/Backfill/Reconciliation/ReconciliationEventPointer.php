<?php

namespace App\Services\Media\Backfill\Reconciliation;

final readonly class ReconciliationEventPointer
{
    private function __construct(
        public bool $present,
        public bool $valid,
        public ?string $fingerprint,
    ) {}

    public static function from(mixed $value): self
    {
        if ($value === null) {
            return new self(false, true, null);
        }

        if (! is_string($value)) {
            return new self(true, false, null);
        }

        $valid = preg_match('/\A[0-9a-f]{8}(?:-[0-9a-f]{4}){3}-[0-9a-f]{12}\z/D', $value) === 1;

        return new self(
            true,
            $valid,
            substr(hash('sha256', $value), 0, 16),
        );
    }
}
