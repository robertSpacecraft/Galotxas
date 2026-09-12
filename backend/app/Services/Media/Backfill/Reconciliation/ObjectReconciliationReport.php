<?php

namespace App\Services\Media\Backfill\Reconciliation;

use App\Services\Media\Backfill\Safety\CleanupState;
use App\Services\Media\Backfill\Safety\CreateState;
use App\Services\Media\Backfill\Safety\ObjectKind;
use App\Services\Media\Backfill\Safety\ObjectWriteState;

final readonly class ObjectReconciliationReport
{
    public function __construct(
        public int $id,
        public int $itemId,
        public string $keyFingerprint,
        public ?ObjectKind $kind,
        public ?ObjectWriteState $writeState,
        public ?CreateState $createState,
        public ?CleanupState $cleanupState,
        public ObjectObservation $observation,
        public ObjectAttribution $attribution,
        public ObjectClassification $classification,
        public bool $hasEtag,
        public bool $hasVersionIdentity,
        public ?ObjectReconciliationResolution $reconciliationResolution,
        public bool $reconciliationResolutionInvalid,
        public bool $hasReconciliationEvent,
        public ?string $reconciliationEventFingerprint,
    ) {}

    public function preventsTrustworthyClassification(): bool
    {
        return $this->classification === ObjectClassification::Inconsistent
            || $this->reconciliationResolutionInvalid
            || in_array($this->observation, [ObjectObservation::Unreadable], true);
    }

    public function recoveryBlocker(): bool
    {
        return in_array($this->writeState, [ObjectWriteState::Intent, ObjectWriteState::Unknown], true)
            || in_array($this->cleanupState, [CleanupState::Pending, CleanupState::Failed, CleanupState::Unknown], true);
    }

    public function recommendedAction(): string
    {
        if ($this->preventsTrustworthyClassification()) {
            return 'investigate_unreadable_or_inconsistent_evidence';
        }
        if (in_array($this->cleanupState, [CleanupState::Pending, CleanupState::Failed, CleanupState::Unknown], true)) {
            return 'cleanup_review_required_later';
        }
        if ($this->recoveryBlocker()) {
            return 'durable_reconciliation_required_later';
        }

        return 'none';
    }
}
