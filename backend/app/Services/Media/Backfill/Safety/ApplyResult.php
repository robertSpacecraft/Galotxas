<?php

namespace App\Services\Media\Backfill\Safety;

enum ApplyResult: string
{
    case Published = 'published';
    case Skipped = 'skipped';
    case ReferenceChanged = 'reference_changed';
    case CollisionDetected = 'collision_detected';
    case FailedCompensated = 'failed_compensated';
    case FailedCleanupIncomplete = 'failed_cleanup_incomplete';
    case FailedNoWrites = 'failed_no_writes';
    case PublicationUnknown = 'publication_unknown';
}
