<?php

namespace App\Services\Media\Backfill;

enum ObjectInspectionState: string
{
    case Present = 'present';
    case Missing = 'missing';
    case InspectionFailed = 'inspection_failed';
}
