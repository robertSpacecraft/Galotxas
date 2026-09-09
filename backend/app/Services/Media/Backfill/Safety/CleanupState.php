<?php

namespace App\Services\Media\Backfill\Safety;

enum CleanupState: string
{
    case NotRequired = 'not_required';
    case Pending = 'pending';
    case Deleted = 'deleted';
    case Failed = 'failed';
    case Unknown = 'unknown';
}
