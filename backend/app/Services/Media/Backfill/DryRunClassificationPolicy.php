<?php

namespace App\Services\Media\Backfill;

final class DryRunClassificationPolicy
{
    public static function isCandidate(PreflightClassification $classification): bool
    {
        return $classification === PreflightClassification::LegacyBackfillable;
    }

    public static function isBlocking(PreflightClassification $classification): bool
    {
        return ! in_array($classification, [
            PreflightClassification::ResponsiveOk,
            PreflightClassification::ExcludedNull,
            PreflightClassification::ExcludedDeleted,
            PreflightClassification::LegacyBackfillable,
        ], true);
    }
}
