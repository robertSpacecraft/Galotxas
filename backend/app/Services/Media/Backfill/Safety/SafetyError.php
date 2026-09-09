<?php

namespace App\Services\Media\Backfill\Safety;

enum SafetyError: string
{
    case InvalidInput = 'invalid_input';
    case IllegalTransition = 'illegal_transition';
    case JournalUnavailable = 'journal_unavailable';
    case AmbientTransaction = 'ambient_transaction';
    case MaintenanceRequired = 'maintenance_required';
    case LockLost = 'lock_lost';
    case LockReleaseFailed = 'lock_release_failed';
    case UnsupportedStorage = 'unsupported_storage';
    case IdentityMismatch = 'identity_mismatch';
    case PublicationUnknown = 'publication_unknown';
}
