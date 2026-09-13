<?php

namespace App\Services\Media\Backfill\Reconciliation;

use RuntimeException;

/** Safe, non-durable refusal used only while constructing operational evidence. */
final class OperationalEvidenceException extends RuntimeException
{
    public function __construct(public readonly ReconciliationBlockReason $reason)
    {
        parent::__construct($reason->value);
    }
}
