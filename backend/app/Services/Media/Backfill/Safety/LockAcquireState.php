<?php

namespace App\Services\Media\Backfill\Safety;

enum LockAcquireState: string
{
    case Acquired = 'acquired';
    case Busy = 'busy';
    case Failed = 'failed';
}
