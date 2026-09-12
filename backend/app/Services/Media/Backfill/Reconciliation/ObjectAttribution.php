<?php

namespace App\Services\Media\Backfill\Reconciliation;

enum ObjectAttribution: string
{
    case NotDispatched = 'not_dispatched';
    case CreatedReceipt = 'created_receipt';
    case RejectedCollision = 'rejected_collision';
    case FailedWithoutWrite = 'failed_without_write';
    case AttemptAmbiguous = 'attempt_ambiguous';
    case InconsistentJournal = 'inconsistent_journal';
}
