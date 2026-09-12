<?php

namespace App\Services\Media\Backfill\Reconciliation;

enum ReconciliationEventType: string
{
    case AttemptStarted = 'attempt_started';
    case AttemptBlocked = 'attempt_blocked';
    case ItemForwardAccepted = 'item_forward_accepted';
    case ItemNoEffectClosed = 'item_no_effect_closed';
    case RunClosedAfterReconciliation = 'run_closed_after_reconciliation';
}
