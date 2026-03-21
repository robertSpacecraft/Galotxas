<?php

namespace App\Enums;

enum GameMatchStatus: string
{
    case SCHEDULED = 'scheduled';
    case SUBMITTED = 'submitted';
    case VALIDATED = 'validated';
    case UNDER_REVIEW = 'under_review';
    case POSTPONED = 'postponed';
    case CANCELLED = 'cancelled';
}
