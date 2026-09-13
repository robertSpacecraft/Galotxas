<?php

namespace App\Services\Media\Backfill\Reconciliation;

/** One internal invocation always targets one complete historical APPLY run. */
final readonly class ReconciliationInvocation
{
    public function __construct(public string $runId)
    {
        if (preg_match('/\A[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}\z/D', $runId) !== 1) {
            throw new ReconciliationException(ReconciliationError::InvalidInput);
        }
    }
}
