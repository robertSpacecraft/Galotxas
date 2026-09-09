<?php

namespace App\Services\Media\Backfill\Safety;

enum ItemPhase: string
{
    case Inspected = 'inspected';
    case Revalidated = 'revalidated';
    case Writing = 'writing';
    case Finished = 'finished';
}
