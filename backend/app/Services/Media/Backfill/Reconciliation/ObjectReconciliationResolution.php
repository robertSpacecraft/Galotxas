<?php

namespace App\Services\Media\Backfill\Reconciliation;

enum ObjectReconciliationResolution: string
{
    case ForwardRetained = 'forward_retained';
}
