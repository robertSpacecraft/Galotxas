<?php

namespace App\Services\Media\Backfill\Safety;

use App\Services\Media\Backfill\ManagedMediaDomain;

final readonly class ApplyRunSelection
{
    public function __construct(
        public ManagedMediaDomain $domain,
        public int $afterId,
        public int $limit,
        public int $upperBound,
    ) {
        if ($afterId < 0 || $limit < 1 || $limit > 1000 || $upperBound < 0) {
            throw new BackfillSafetyException(SafetyError::InvalidInput);
        }
    }

    /** @return array{domain: string, after_id: int, limit: int} */
    public function options(): array
    {
        return ['domain' => $this->domain->value, 'after_id' => $this->afterId, 'limit' => $this->limit];
    }

    /** @return array<string, int> */
    public function upperBounds(): array
    {
        return [$this->domain->value => $this->upperBound];
    }

    /** @return array<string, int> */
    public function checkpoints(): array
    {
        return [$this->domain->value => $this->afterId];
    }
}
