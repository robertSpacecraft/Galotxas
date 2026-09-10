<?php

namespace App\Services\Media\Backfill;

final readonly class DryRunAnomaly
{
    /** @param list<string> $reasonCodes */
    public function __construct(
        public ManagedMediaDomain $domain,
        public int $entityId,
        public PreflightClassification $classification,
        public array $reasonCodes,
    ) {}
}
