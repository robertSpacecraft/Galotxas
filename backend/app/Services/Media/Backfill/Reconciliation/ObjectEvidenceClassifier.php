<?php

namespace App\Services\Media\Backfill\Reconciliation;

use App\Services\Media\Backfill\Safety\CleanupState;
use App\Services\Media\Backfill\Safety\CreateState;
use App\Services\Media\Backfill\Safety\ObjectKind;
use App\Services\Media\Backfill\Safety\ObjectWriteState;
use App\Services\Media\Backfill\Safety\TargetObject;
use stdClass;
use Throwable;

class ObjectEvidenceClassifier
{
    /**
     * $linkIsValid carries the read-only semantic validation of the durable event pointer, which
     * needs journal lookups this pure classifier deliberately does not perform.
     */
    public function report(
        stdClass $row,
        ObjectObservation $observation,
        bool $parentConsistent = true,
        bool $linkIsValid = true,
    ): ObjectReconciliationReport {
        $kind = is_string($row->kind ?? null) ? ObjectKind::tryFrom($row->kind) : null;
        $write = is_string($row->write_state ?? null) ? ObjectWriteState::tryFrom($row->write_state) : null;
        $create = is_string($row->create_state ?? null) ? CreateState::tryFrom($row->create_state) : null;
        $cleanup = is_string($row->cleanup_state ?? null) ? CleanupState::tryFrom($row->cleanup_state) : null;
        $resolution = is_string($row->reconciliation_resolution ?? null)
            ? ObjectReconciliationResolution::tryFrom($row->reconciliation_resolution)
            : null;
        $event = ReconciliationEventPointer::from($row->reconciliation_event_id ?? null);
        $resolutionInvalid = (($row->reconciliation_resolution ?? null) !== null && $resolution === null)
            || ($resolution !== null && ! $event->valid)
            || ($resolution !== null) !== $event->present
            || ! $linkIsValid;
        $attribution = $parentConsistent ? $this->attributionFor($row) : ObjectAttribution::InconsistentJournal;
        $consistent = $attribution !== ObjectAttribution::InconsistentJournal;

        return new ObjectReconciliationReport(
            id: (int) ($row->id ?? 0),
            itemId: (int) ($row->item_id ?? 0),
            keyFingerprint: hash('sha256', is_string($row->object_key ?? null) ? $row->object_key : ''),
            kind: $kind,
            writeState: $write,
            createState: $create,
            cleanupState: $cleanup,
            observation: $observation,
            attribution: $attribution,
            classification: $consistent
                ? $this->classification($attribution, $cleanup, $observation)
                : ObjectClassification::Inconsistent,
            hasEtag: is_string($row->etag ?? null) && $row->etag !== '',
            hasVersionIdentity: is_string($row->version_id ?? null) && $row->version_id !== '',
            reconciliationResolution: $resolution,
            reconciliationResolutionInvalid: $resolutionInvalid,
            hasReconciliationEvent: $event->present,
            reconciliationEventFingerprint: $event->fingerprint,
        );
    }

    /** Durable-state attribution only: no storage observation and no parent context. */
    public function attributionFor(stdClass $row): ObjectAttribution
    {
        $kind = is_string($row->kind ?? null) ? ObjectKind::tryFrom($row->kind) : null;
        $write = is_string($row->write_state ?? null) ? ObjectWriteState::tryFrom($row->write_state) : null;
        $create = is_string($row->create_state ?? null) ? CreateState::tryFrom($row->create_state) : null;
        $cleanup = is_string($row->cleanup_state ?? null) ? CleanupState::tryFrom($row->cleanup_state) : null;

        return $write !== null && $this->targetIsValid($row, $kind)
            && $this->statesAreConsistent($row, $write, $create, $cleanup)
            ? $this->attribution($write, $create)
            : ObjectAttribution::InconsistentJournal;
    }

    private function targetIsValid(stdClass $row, ?ObjectKind $kind): bool
    {
        try {
            if ((int) ($row->id ?? 0) < 1 || (int) ($row->item_id ?? 0) < 1 || $kind === null
                || ! is_string($row->object_key ?? null) || ! is_string($row->expected_sha256 ?? null)
                || ! is_numeric($row->expected_size ?? null) || ! is_string($row->mime_type ?? null)) {
                return false;
            }
            new TargetObject(
                $row->object_key,
                $kind,
                $row->expected_sha256,
                (int) $row->expected_size,
                $row->mime_type,
            );

            return true;
        } catch (Throwable) {
            return false;
        }
    }

    private function statesAreConsistent(
        stdClass $row,
        ?ObjectWriteState $write,
        ?CreateState $create,
        ?CleanupState $cleanup,
    ): bool {
        if ($write === null || $cleanup === null || ($row->create_state ?? null) !== $create?->value
            || ! $this->receiptIdentityIsValid($row)) {
            return false;
        }

        $intent = ($row->write_intent_at ?? null) !== null;
        $confirmed = ($row->write_confirmed_at ?? null) !== null;
        $hasReceiptIdentity = ($row->etag ?? null) !== null || ($row->version_id ?? null) !== null;
        $writeConsistent = match ($write) {
            ObjectWriteState::Planned => $create === null && ! $intent && ! $confirmed && ! $hasReceiptIdentity,
            ObjectWriteState::Intent => $create === null && $intent && ! $confirmed && ! $hasReceiptIdentity,
            ObjectWriteState::Created => $create === CreateState::Created && $intent && $confirmed,
            ObjectWriteState::Unknown => $create === CreateState::Unknown && $intent && ! $confirmed && ! $hasReceiptIdentity,
            ObjectWriteState::Rejected => in_array($create, [CreateState::Rejected, CreateState::Failed], true)
                && $intent && ! $confirmed && ! $hasReceiptIdentity,
        };
        if (! $writeConsistent || ($cleanup !== CleanupState::NotRequired && $write !== ObjectWriteState::Created)) {
            return false;
        }

        $attempted = ($row->cleanup_attempted_at ?? null) !== null;
        $finished = ($row->cleanup_finished_at ?? null) !== null;

        return match ($cleanup) {
            CleanupState::NotRequired => ! $attempted && ! $finished,
            CleanupState::Pending, CleanupState::Failed, CleanupState::Unknown => $attempted && ! $finished,
            CleanupState::Deleted => $attempted && $finished,
        };
    }

    private function receiptIdentityIsValid(stdClass $row): bool
    {
        foreach (['etag', 'version_id'] as $field) {
            $value = $row->$field ?? null;
            if ($value !== null && (! is_string($value) || $value === '')) {
                return false;
            }
        }

        return true;
    }

    private function attribution(ObjectWriteState $write, ?CreateState $create): ObjectAttribution
    {
        return match ($write) {
            ObjectWriteState::Planned => ObjectAttribution::NotDispatched,
            ObjectWriteState::Intent, ObjectWriteState::Unknown => ObjectAttribution::AttemptAmbiguous,
            ObjectWriteState::Created => ObjectAttribution::CreatedReceipt,
            ObjectWriteState::Rejected => $create === CreateState::Rejected
                ? ObjectAttribution::RejectedCollision
                : ObjectAttribution::FailedWithoutWrite,
        };
    }

    private function classification(
        ObjectAttribution $attribution,
        CleanupState $cleanup,
        ObjectObservation $observation,
    ): ObjectClassification {
        if (in_array($cleanup, [CleanupState::Pending, CleanupState::Failed, CleanupState::Unknown], true)) {
            if ($observation === ObjectObservation::Unreadable) {
                return ObjectClassification::CleanupUnreadable;
            }
            $absent = $observation === ObjectObservation::AbsentNow;

            return match ($cleanup) {
                CleanupState::Pending => $absent
                    ? ObjectClassification::CleanupPendingAbsentNow
                    : ObjectClassification::CleanupPendingPresent,
                CleanupState::Failed => $absent
                    ? ObjectClassification::CleanupFailedAbsentNow
                    : ObjectClassification::CleanupFailedPresent,
                CleanupState::Unknown => $absent
                    ? ObjectClassification::CleanupUnknownAbsentNow
                    : ObjectClassification::CleanupUnknownPresent,
                default => ObjectClassification::Inconsistent,
            };
        }
        if ($cleanup === CleanupState::Deleted || $attribution === ObjectAttribution::NotDispatched) {
            return ObjectClassification::ResolvedByJournal;
        }

        return match ($attribution) {
            ObjectAttribution::AttemptAmbiguous => match ($observation) {
                ObjectObservation::AbsentNow => ObjectClassification::AmbiguousAbsentNow,
                ObjectObservation::ExpectedContentPresent => ObjectClassification::AmbiguousExpectedPresent,
                ObjectObservation::DifferentContentPresent => ObjectClassification::AmbiguousDifferentPresent,
                ObjectObservation::Unreadable => ObjectClassification::AmbiguousUnreadable,
            },
            ObjectAttribution::CreatedReceipt => match ($observation) {
                ObjectObservation::AbsentNow => ObjectClassification::CreatedMissingNow,
                ObjectObservation::ExpectedContentPresent => ObjectClassification::CreatedExpectedPresent,
                ObjectObservation::DifferentContentPresent => ObjectClassification::CreatedDifferentPresent,
                ObjectObservation::Unreadable => ObjectClassification::CreatedUnreadable,
            },
            ObjectAttribution::RejectedCollision => ObjectClassification::Collision,
            ObjectAttribution::FailedWithoutWrite => ObjectClassification::KnownFailedWithoutWrite,
            ObjectAttribution::NotDispatched => ObjectClassification::ResolvedByJournal,
            ObjectAttribution::InconsistentJournal => ObjectClassification::Inconsistent,
        };
    }
}
