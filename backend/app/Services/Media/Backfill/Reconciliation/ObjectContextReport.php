<?php

namespace App\Services\Media\Backfill\Reconciliation;

use App\Services\Media\Backfill\Safety\ItemPhase;
use App\Services\Media\Backfill\Safety\RunState;

final readonly class ObjectContextReport
{
    public function __construct(
        public ObjectReconciliationReport $object,
        public string $runId,
        public ?RunState $runState,
        public int $itemId,
        public string $domain,
        public int $entityId,
        public ?ItemPhase $itemPhase,
    ) {}

    public function preventsTrustworthyClassification(): bool
    {
        return $this->runState === null || $this->itemPhase === null
            || $this->object->preventsTrustworthyClassification();
    }
}
