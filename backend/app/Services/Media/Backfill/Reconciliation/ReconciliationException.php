<?php

namespace App\Services\Media\Backfill\Reconciliation;

use RuntimeException;

final class ReconciliationException extends RuntimeException
{
    // Deliberately exclude previous exceptions: SQL/SDK messages can contain secrets.
    public function __construct(public readonly ReconciliationError $reason)
    {
        parent::__construct($reason->value);
    }
}
