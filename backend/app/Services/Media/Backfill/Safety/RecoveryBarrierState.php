<?php

namespace App\Services\Media\Backfill\Safety;

enum RecoveryBarrierState: string
{
    case Clear = 'clear';
    case Blocked = 'blocked';
}
