<?php

namespace App\Services\Media\Backfill\Safety;

enum CreateState: string
{
    case Created = 'created';
    case Rejected = 'rejected';
    case Unknown = 'unknown';
    case Failed = 'failed';
}
