<?php

namespace App\Services\Media\Backfill\Reconciliation;

enum ObjectObservation: string
{
    case AbsentNow = 'absent_now';
    case ExpectedContentPresent = 'expected_content_present';
    case DifferentContentPresent = 'different_content_present';
    case Unreadable = 'unreadable';
}
