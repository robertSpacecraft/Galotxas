<?php

namespace App\Services\Media\Backfill\Reconciliation;

/**
 * Read-only C2 gate for the external-writer part of the observation/commit boundary.
 *
 * The current project can prove neither that lifecycle writers outside HTTP are stopped nor that
 * an external S3 client cannot write a canonical key. Maintenance and the backfill advisory lock
 * are cooperative and do not provide that proof. This guard therefore has no permissive runtime
 * state or caller-controlled override: forward reconciliation remains deliberately unavailable.
 */
final class ManagedMediaWriterFreezeGuard
{
    public function assertEstablished(ReconciliationBackendMode $backendMode): void
    {
        match ($backendMode) {
            ReconciliationBackendMode::Local,
            ReconciliationBackendMode::S3 => throw new OperationalEvidenceException(
                ReconciliationBlockReason::StorageObservationUntrusted,
            ),
        };
    }
}
