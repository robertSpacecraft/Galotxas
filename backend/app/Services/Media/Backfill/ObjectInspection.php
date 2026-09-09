<?php

namespace App\Services\Media\Backfill;

final readonly class ObjectInspection
{
    public function __construct(
        public ObjectInspectionState $state,
        public ?string $bytes = null,
        public ?InspectionReason $reason = null,
    ) {}
}
