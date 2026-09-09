<?php

namespace App\Services\Media\Backfill;

use App\Services\Media\PreparedResponsiveDerivatives;

final readonly class PreflightResult
{
    /** @param list<InspectionReason> $reasons
     * @param  list<string>  $observedKeys
     */
    public function __construct(
        public ManagedMediaReference $reference,
        public PreflightClassification $classification,
        public array $reasons = [],
        public array $observedKeys = [],
        public ?PreparedResponsiveDerivatives $prepared = null,
    ) {}
}
