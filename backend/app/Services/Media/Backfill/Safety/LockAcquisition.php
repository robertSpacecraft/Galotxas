<?php

namespace App\Services\Media\Backfill\Safety;

final readonly class LockAcquisition
{
    public function __construct(public LockAcquireState $state, public ?AdvisoryLockHandle $handle = null) {}
}
