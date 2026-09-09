<?php

namespace App\Services\Media\Backfill;

use App\Services\Media\ResponsiveManifest;

final readonly class ManifestInspection
{
    public function __construct(
        public ManifestInspectionState $state,
        public ?ResponsiveManifest $manifest = null,
        public ?InspectionReason $reason = null,
    ) {}
}
