<?php

namespace App\Enums;

enum SeasonStatus: string
{
    case PLANNED = 'planned';
    case ACTIVE = 'active';
    case FINISHED = 'finished';
    case CANCELLED = 'cancelled';
}
