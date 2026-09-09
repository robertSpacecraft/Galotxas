<?php

namespace App\Services\Media\Backfill;

enum ManifestInspectionState: string
{
    case Missing = 'missing';
    case Valid = 'valid';
    case Invalid = 'invalid';
    case InspectionFailed = 'inspection_failed';
}
