<?php

namespace App\Services\Media\Backfill\Reconciliation;

enum RunFlag: string
{
    case CleanTerminal = 'clean_terminal';
    case ActiveNoOtherBlocker = 'active_no_other_blocker';
    case HasUnfinishedItems = 'has_unfinished_items';
    case HasAmbiguousWrites = 'has_ambiguous_writes';
    case HasCleanupAttention = 'has_cleanup_attention';
    case StorageIdentityMismatch = 'storage_identity_mismatch';
    case DetailsTruncated = 'details_truncated';
    case Inconsistent = 'inconsistent';
}
