<?php

namespace App\Services\Media\Backfill\Reconciliation;

/** Why one attempt refused to resolve. Never a resolution and never a cleared blocker. */
enum ReconciliationBlockReason: string
{
    case InconsistentJournal = 'inconsistent_journal';
    case EvidenceMismatch = 'evidence_mismatch';
    case DomainRevalidationFailed = 'domain_revalidation_failed';
    case StorageObservationUntrusted = 'storage_observation_untrusted';
    case ResolutionConflict = 'resolution_conflict';
}
