<?php

namespace App\Enums;

enum MatchResultReportStatus: string
{
    case SUBMITTED = 'submitted';
    case VALIDATED = 'validated';
    case CONFLICT = 'conflict';
}
