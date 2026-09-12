<?php

namespace App\Services\Media\Backfill\Reconciliation;

enum ObjectClassification: string
{
    case ResolvedByJournal = 'resolved_by_journal';
    case AmbiguousAbsentNow = 'ambiguous_absent_now';
    case AmbiguousExpectedPresent = 'ambiguous_expected_present';
    case AmbiguousDifferentPresent = 'ambiguous_different_present';
    case AmbiguousUnreadable = 'ambiguous_unreadable';
    case CreatedExpectedPresent = 'created_expected_present';
    case CreatedMissingNow = 'created_missing_now';
    case CreatedDifferentPresent = 'created_different_present';
    case CreatedUnreadable = 'created_unreadable';
    case Collision = 'collision';
    case KnownFailedWithoutWrite = 'known_failed_without_write';
    case CleanupPendingPresent = 'cleanup_pending_present';
    case CleanupPendingAbsentNow = 'cleanup_pending_absent_now';
    case CleanupFailedPresent = 'cleanup_failed_present';
    case CleanupFailedAbsentNow = 'cleanup_failed_absent_now';
    case CleanupUnknownPresent = 'cleanup_unknown_present';
    case CleanupUnknownAbsentNow = 'cleanup_unknown_absent_now';
    case CleanupUnreadable = 'cleanup_unreadable';
    case Inconsistent = 'inconsistent';
}
