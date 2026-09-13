<?php

namespace App\Services\Media\Backfill\Reconciliation;

enum ReconciliationItemOperationalState: string
{
    case OrdinaryNonBlocking = 'ordinary_nonblocking';
    case NoEffectCandidate = 'no_effect_candidate';
    case ForwardCandidate = 'forward_candidate';
    case UnresolvedBlocked = 'unresolved_blocked';
    case ResolvedForward = 'resolved_forward';
    case ResolvedNoEffect = 'resolved_no_effect';
    case Inconsistent = 'inconsistent';
}
