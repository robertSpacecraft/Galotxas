<?php

namespace App\Services\Media\Backfill\Reconciliation;

use App\Services\Media\Backfill\Safety\RunState;

final readonly class RunReconciliationReport
{
    /**
     * @param  list<RunFlag>  $flags
     * @param  list<ItemReconciliationReport>  $items
     */
    public function __construct(
        public string $runId,
        public ?RunState $state,
        public string $domain,
        public int $afterId,
        public int $limit,
        public int $upperBound,
        public int $checkpoint,
        public int $totalItems,
        public int $unfinishedItems,
        public int $unresolvedObjects,
        public int $ambiguousWrites,
        public int $cleanupAttention,
        public ?bool $storageIdentityMatches,
        public array $flags,
        public array $items,
        public bool $itemsTruncated,
        public bool $hasReconciliationEvent,
        public bool $reconciliationEventPointerInvalid,
        public ?string $reconciliationEventFingerprint,
    ) {}

    public function preventsTrustworthyClassification(): bool
    {
        if ($this->reconciliationEventPointerInvalid
            || in_array(RunFlag::Inconsistent, $this->flags, true)
            || in_array(RunFlag::StorageIdentityMismatch, $this->flags, true)) {
            return true;
        }
        foreach ($this->items as $item) {
            if ($item->preventsTrustworthyClassification()) {
                return true;
            }
        }

        return false;
    }
}
