<?php

namespace App\Services\Media\Backfill;

use App\Services\Media\Backfill\Safety\BackfillSafetyException;
use App\Services\Media\Backfill\Safety\SafetyError;

final readonly class ApplyInvocation
{
    public function __construct(
        public ManagedMediaDomain $domain,
        public int $afterId,
        public int $limit,
    ) {
        if ($afterId < 0 || $limit < 1 || $limit > 1000) {
            throw new BackfillSafetyException(SafetyError::InvalidInput);
        }
    }
}
