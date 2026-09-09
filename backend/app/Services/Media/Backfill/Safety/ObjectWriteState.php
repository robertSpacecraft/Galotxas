<?php

namespace App\Services\Media\Backfill\Safety;

enum ObjectWriteState: string
{
    case Planned = 'planned';
    case Intent = 'intent';
    case Created = 'created';
    case Unknown = 'unknown';
    case Rejected = 'rejected';
}
