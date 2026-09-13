<?php

namespace App\Services\Media\Backfill\Reconciliation;

/** Internal outcome taxonomy reserved for the future C3 exit mapping. */
enum ReconciliationOutcome: string
{
    case Completed = 'completed';
    case Blocked = 'blocked';
    case AlreadyClosed = 'already_closed';
    case NoReconciliationRequired = 'no_reconciliation_required';
    case MaintenanceRequired = 'maintenance_required';
    case LockBusy = 'lock_busy';
    case LockAcquireFailed = 'lock_acquire_failed';
    case SafetyFailure = 'safety_failure';
}
