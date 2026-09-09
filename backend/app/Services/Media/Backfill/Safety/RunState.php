<?php

namespace App\Services\Media\Backfill\Safety;

enum RunState: string
{
    case Active = 'active';
    case Completed = 'completed';
    case Failed = 'failed';
    case Interrupted = 'interrupted';
}
