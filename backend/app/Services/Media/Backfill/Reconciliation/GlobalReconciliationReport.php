<?php

namespace App\Services\Media\Backfill\Reconciliation;

use App\Services\Media\Backfill\Safety\RecoveryBarrierState;

final readonly class GlobalReconciliationReport
{
    /**
     * @param  list<RunReconciliationReport>  $activeRuns
     * @param  list<ItemReconciliationReport>  $unfinishedItems
     * @param  list<ObjectReconciliationReport>  $unresolvedObjects
     */
    public function __construct(
        public RecoveryBarrierState $barrier,
        public array $activeRuns,
        public array $unfinishedItems,
        public array $unresolvedObjects,
        public int $afterObjectId,
        public int $limit,
    ) {}

    public function preventsTrustworthyClassification(): bool
    {
        foreach ([...$this->activeRuns, ...$this->unfinishedItems, ...$this->unresolvedObjects] as $report) {
            if ($report->preventsTrustworthyClassification()) {
                return true;
            }
        }

        return false;
    }
}
