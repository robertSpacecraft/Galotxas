<?php

namespace App\Services\Media\Backfill;

enum PreflightClassification: string
{
    case ExcludedNull = 'excluded_null';
    case ExcludedDeleted = 'excluded_deleted';
    case ResponsiveOk = 'responsive_ok';
    case LegacyBackfillable = 'legacy_backfillable';
    case InvalidReference = 'invalid_reference';
    case ReferenceConflict = 'reference_conflict';
    case MasterMissing = 'master_missing';
    case MasterUnprocessable = 'master_unprocessable';
    case ManifestInvalid = 'manifest_invalid';
    case ResponsiveIncomplete = 'responsive_incomplete';
    case PartialCollision = 'partial_collision';
    case MetadataMismatch = 'metadata_mismatch';
    case InspectionFailed = 'inspection_failed';
}
