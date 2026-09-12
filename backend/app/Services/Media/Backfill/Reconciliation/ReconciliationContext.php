<?php

namespace App\Services\Media\Backfill\Reconciliation;

use App\Services\Media\Backfill\Safety\AdvisoryLockHandle;
use App\Services\Media\Backfill\Safety\StorageIdentity;

/**
 * Accepted operational context of one reconciliation attempt. The future coordinator owns the
 * advisory lock lifetime: this value object only carries the handle so the repository can verify
 * ownership. It never acquires, releases or replaces a lock.
 */
final readonly class ReconciliationContext
{
    public function __construct(
        public string $attemptId,
        public AdvisoryLockHandle $lock,
        public StorageIdentity $identity,
        public ReconciliationBackendMode $backendMode,
        public ?string $codeRevision = null,
    ) {
        if (preg_match('/\A[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}\z/D', $attemptId) !== 1
            || ($codeRevision !== null && preg_match('/\A[0-9a-f]{40}\z/D', $codeRevision) !== 1)) {
            throw new ReconciliationException(ReconciliationError::InvalidInput);
        }
        if (! hash_equals($identity->hash, $lock->identity->hash)) {
            throw new ReconciliationException(ReconciliationError::IdentityMismatch);
        }
    }
}
