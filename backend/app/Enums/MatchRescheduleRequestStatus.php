<?php

namespace App\Enums;

enum MatchRescheduleRequestStatus: string
{
    case SUBMITTED = 'submitted';
    case VALIDATED = 'validated';
}
