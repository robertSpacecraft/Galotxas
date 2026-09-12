<?php

namespace App\Services\Media\Backfill\Reconciliation;

/** Closed D2 mutation taxonomy. Every case is fail-closed and operator-safe. */
enum ReconciliationError: string
{
    case InvalidInput = 'invalid_input';
    case MaintenanceRequired = 'maintenance_required';
    case LockLost = 'lock_lost';
    case IdentityMismatch = 'identity_mismatch';
    case UnsupportedStorage = 'unsupported_storage';
    case AmbientTransaction = 'ambient_transaction';
    case JournalUnavailable = 'journal_unavailable';
    case InconsistentJournal = 'inconsistent_journal';
    case EvidenceMismatch = 'evidence_mismatch';
    case ReplayConflict = 'replay_conflict';
    case IllegalResolution = 'illegal_resolution';
}
