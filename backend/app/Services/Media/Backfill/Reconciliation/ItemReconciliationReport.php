<?php

namespace App\Services\Media\Backfill\Reconciliation;

use App\Services\Media\Backfill\Safety\ApplyResult;
use App\Services\Media\Backfill\Safety\ItemPhase;

final readonly class ItemReconciliationReport
{
    /**
     * @param  list<ObjectReconciliationReport>  $objects
     * @param  array<string, int>  $durableCounts
     * @param  array<string, int>  $classificationCounts
     */
    public function __construct(
        public int $id,
        public string $runId,
        public string $domain,
        public int $entityId,
        public ?ItemPhase $phase,
        public ?ApplyResult $applyResult,
        public string $preflightClassification,
        public array $objects,
        public array $durableCounts,
        public array $classificationCounts,
        public ?ObjectReconciliationReport $manifest,
        public ItemClassification $classification,
        public bool $domainRevalidationPending,
    ) {}

    public function preventsTrustworthyClassification(): bool
    {
        if ($this->classification === ItemClassification::InternallyInconsistent) {
            return true;
        }
        foreach ($this->objects as $object) {
            if ($object->preventsTrustworthyClassification()) {
                return true;
            }
        }

        return false;
    }

    public function recommendedAction(): string
    {
        return match ($this->classification) {
            ItemClassification::StorageSetExactDomainRevalidationPending => 'review_forward_reconciliation_in_d2_c',
            ItemClassification::CleanupAttentionCandidate => 'cleanup_review_required_later',
            ItemClassification::AmbiguousBlocked => 'durable_reconciliation_required_later',
            ItemClassification::InternallyInconsistent => 'investigate_inconsistent_evidence',
            ItemClassification::NoPublicationObservedNow => 'review_no_publication_evidence_in_d2_c',
        };
    }
}
