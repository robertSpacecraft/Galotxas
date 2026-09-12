<?php

namespace App\Services\Media\Backfill\Reconciliation;

enum ItemClassification: string
{
    case StorageSetExactDomainRevalidationPending = 'storage_set_exact_domain_revalidation_pending_d2_c';
    case NoPublicationObservedNow = 'no_publication_observed_now';
    case CleanupAttentionCandidate = 'cleanup_attention_candidate';
    case AmbiguousBlocked = 'ambiguous_blocked';
    case InternallyInconsistent = 'internally_inconsistent';
}
