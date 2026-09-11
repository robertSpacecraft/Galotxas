<?php

namespace App\Services\Media\Backfill;

enum ApplyOutcome: string
{
    case Success = 'success';
    case FirstPassBlocked = 'first_pass_blocked';
    case MaintenanceRequired = 'maintenance_required';
    case LockBusy = 'lock_busy';
    case LockAcquireFailed = 'lock_acquire_failed';
    case SafeFailure = 'safe_failure';
    case ReconciliationRequired = 'reconciliation_required';
}
