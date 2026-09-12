<?php

namespace App\Services\Media\Backfill\Reconciliation;

enum ItemReconciliationResult: string
{
    case ForwardAccepted = 'forward_accepted';
    case ClosedNoEffect = 'closed_no_effect';
}
