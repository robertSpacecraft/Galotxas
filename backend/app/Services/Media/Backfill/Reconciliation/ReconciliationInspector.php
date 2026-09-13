<?php

namespace App\Services\Media\Backfill\Reconciliation;

use App\Services\Media\Backfill\ManagedMediaDomain;
use App\Services\Media\Backfill\PreflightClassification;
use App\Services\Media\Backfill\Safety\ApplyJournal;
use App\Services\Media\Backfill\Safety\ApplyResult;
use App\Services\Media\Backfill\Safety\CleanupState;
use App\Services\Media\Backfill\Safety\ItemPhase;
use App\Services\Media\Backfill\Safety\JournalMode;
use App\Services\Media\Backfill\Safety\ObjectKind;
use App\Services\Media\Backfill\Safety\ObjectWriteState;
use App\Services\Media\Backfill\Safety\RunState;
use App\Services\Media\Backfill\Safety\StorageIdentity;
use App\Services\Media\ManifestImage;
use App\Services\Media\ResponsiveManifest;
use Illuminate\Database\DatabaseManager;
use stdClass;
use Throwable;

/** Coordinates bounded journal reads and exact-key observations. No recovery transitions exist here. */
class ReconciliationInspector
{
    private const MAX_CHILDREN = 1000;

    public function __construct(
        private readonly ApplyJournal $journal,
        private readonly ExactObjectObserver $observer,
        private readonly ObjectEvidenceClassifier $classifier,
        private readonly CandidateManifestReader $candidates,
        private readonly ReconciliationEventValidator $events,
        private readonly ReconciliationStateValidator $states,
        private readonly DatabaseManager $database,
    ) {}

    public function inspectObject(int $objectId): ObjectReconciliationReport
    {
        return $this->inspectObjectContext($objectId)->object;
    }

    public function inspectObjectContext(int $objectId): ObjectContextReport
    {
        $object = $this->journal->object($objectId);
        $item = $this->journal->item((int) $object->item_id);
        $run = $this->journal->run($item->run_id);
        [$manifest, , , , $itemConsistent] = $this->itemContext($item, $run);

        return new ObjectContextReport(
            object: $this->objectReport($object, $item, $run, $manifest, $itemConsistent),
            runId: (string) ($run->run_id ?? ''),
            runState: is_string($run->state ?? null) ? RunState::tryFrom($run->state) : null,
            itemId: (int) ($item->id ?? 0),
            domain: is_string($item->domain ?? null) ? $item->domain : 'invalid',
            entityId: (int) ($item->entity_id ?? 0),
            itemPhase: is_string($item->phase ?? null) ? ItemPhase::tryFrom($item->phase) : null,
        );
    }

    public function inspectItem(int $itemId): ItemReconciliationReport
    {
        $item = $this->journal->item($itemId);
        $run = $this->journal->run($item->run_id);

        return $this->itemReport($item, $run);
    }

    public function inspectRun(string $runId, int $detailLimit): RunReconciliationReport
    {
        if ($detailLimit < 1 || $detailLimit > self::MAX_CHILDREN) {
            throw new \InvalidArgumentException('Invalid detail limit.');
        }

        return $this->runReport($this->journal->run($runId), $detailLimit);
    }

    public function inspectGlobal(int $afterObjectId = 0, int $limit = 100): GlobalReconciliationReport
    {
        $activeRuns = array_map(
            fn (stdClass $run): RunReconciliationReport => $this->runReport($run, 0),
            $this->journal->activeRuns($limit),
        );
        $unfinishedItems = array_map(
            function (stdClass $item): ItemReconciliationReport {
                return $this->itemReport($item, $this->journal->run($item->run_id));
            },
            $this->journal->allUnfinishedItems(0, $limit),
        );
        $unresolvedObjects = array_map(
            function (stdClass $object): ObjectReconciliationReport {
                $item = $this->journal->item((int) $object->item_id);
                $run = $this->journal->run($item->run_id);
                [$manifest, , , , $itemConsistent] = $this->itemContext($item, $run);

                return $this->objectReport($object, $item, $run, $manifest, $itemConsistent);
            },
            $this->journal->unresolvedObjects($afterObjectId, $limit),
        );

        return new GlobalReconciliationReport(
            $this->journal->recoveryBarrier(),
            $activeRuns,
            $unfinishedItems,
            $unresolvedObjects,
            $afterObjectId,
            $limit,
        );
    }

    private function runReport(stdClass $run, int $detailLimit): RunReconciliationReport
    {
        [$domain, $afterId, $limit, $upperBound, $checkpoint, $runConsistent] = $this->runSelection($run);
        $counts = $this->journal->runEvidenceCounts((string) $run->run_id);
        $itemRows = $detailLimit > 0
            ? $this->journal->itemsForRun((string) $run->run_id, 0, $detailLimit)
            : [];
        $items = array_map(fn (stdClass $item): ItemReconciliationReport => $this->itemReport($item, $run), $itemRows);
        $state = is_string($run->state ?? null) ? RunState::tryFrom($run->state) : null;
        $reconciliationEvent = ReconciliationEventPointer::from($run->reconciliation_event_id ?? null);
        // A run pointer may only name a valid late-closure event; D2-B2 never writes one.
        $runLinkValid = $reconciliationEvent->valid && (! $reconciliationEvent->present
            || $this->states->runClosureProjectionIsValid($run));
        $identityMatches = $this->storageIdentityMatches($run);
        $itemsTruncated = $counts['total_items'] > count($itemRows);
        $childInconsistent = false;
        foreach ($items as $item) {
            if ($item->classification === ItemClassification::InternallyInconsistent) {
                $childInconsistent = true;
                break;
            }
        }
        $inconsistent = ! $runConsistent || ! $runLinkValid
            || $counts['total_items'] > $limit || $childInconsistent;
        $flags = [];
        if ($counts['unfinished_items'] > 0) {
            $flags[] = RunFlag::HasUnfinishedItems;
        }
        if ($counts['ambiguous_writes'] > 0) {
            $flags[] = RunFlag::HasAmbiguousWrites;
        }
        if ($counts['cleanup_attention'] > 0) {
            $flags[] = RunFlag::HasCleanupAttention;
        }
        if ($identityMatches !== true) {
            $flags[] = RunFlag::StorageIdentityMismatch;
        }
        if ($itemsTruncated) {
            $flags[] = RunFlag::DetailsTruncated;
        }
        if ($inconsistent) {
            $flags[] = RunFlag::Inconsistent;
        }
        $hasOtherBlocker = $counts['unfinished_items'] > 0 || $counts['unresolved_objects'] > 0
            || $identityMatches !== true || $itemsTruncated || $inconsistent;
        if ($state === RunState::Active && ! $hasOtherBlocker) {
            $flags[] = RunFlag::ActiveNoOtherBlocker;
        } elseif ($state !== null && $state !== RunState::Active && ! $hasOtherBlocker) {
            $flags[] = RunFlag::CleanTerminal;
        }

        return new RunReconciliationReport(
            runId: (string) ($run->run_id ?? ''),
            state: $state,
            domain: $domain,
            afterId: $afterId,
            limit: $limit,
            upperBound: $upperBound,
            checkpoint: $checkpoint,
            totalItems: $counts['total_items'],
            unfinishedItems: $counts['unfinished_items'],
            unresolvedObjects: $counts['unresolved_objects'],
            ambiguousWrites: $counts['ambiguous_writes'],
            cleanupAttention: $counts['cleanup_attention'],
            storageIdentityMatches: $identityMatches,
            flags: $flags,
            items: $items,
            itemsTruncated: $itemsTruncated,
            hasReconciliationEvent: $reconciliationEvent->present,
            reconciliationEventPointerInvalid: ! $runLinkValid,
            reconciliationEventFingerprint: $reconciliationEvent->fingerprint,
        );
    }

    private function itemReport(stdClass $item, stdClass $run): ItemReconciliationReport
    {
        [$manifest, $phase, $result, $preflight, $itemConsistent] = $this->itemContext($item, $run);
        $reconciliationResult = is_string($item->reconciliation_result ?? null)
            ? ItemReconciliationResult::tryFrom($item->reconciliation_result)
            : null;
        $reconciliationEvent = ReconciliationEventPointer::from($item->reconciliation_event_id ?? null);
        $reconciliationResultInvalid = (($item->reconciliation_result ?? null) !== null
            && $reconciliationResult === null)
            || ($reconciliationResult !== null && ! $reconciliationEvent->valid)
            || ($reconciliationResult !== null) !== $reconciliationEvent->present
            || ($reconciliationResult !== null && ! $this->itemLinkIsValid($item, $run, $reconciliationResult));
        $rows = $this->journal->objectsForItem((int) $item->id, 0, self::MAX_CHILDREN);
        $objects = array_map(
            fn (stdClass $object): ObjectReconciliationReport => $this->objectReport(
                $object,
                $item,
                $run,
                $manifest,
                $itemConsistent,
            ),
            $rows,
        );
        $durableCounts = [];
        $classificationCounts = [];
        $manifestReport = null;
        $hasCleanupAttention = false;
        $hasAmbiguity = false;
        $hasInconsistency = ! $itemConsistent || $reconciliationResultInvalid;
        foreach ($objects as $object) {
            $durableKey = ($object->writeState?->value ?? 'invalid').'/'.($object->cleanupState?->value ?? 'invalid');
            $durableCounts[$durableKey] = ($durableCounts[$durableKey] ?? 0) + 1;
            $classificationCounts[$object->classification->value] = ($classificationCounts[$object->classification->value] ?? 0) + 1;
            $manifestReport = $object->kind === ObjectKind::Manifest ? $object : $manifestReport;
            $hasCleanupAttention = $hasCleanupAttention || in_array(
                $object->cleanupState,
                [CleanupState::Pending, CleanupState::Failed, CleanupState::Unknown],
                true,
            );
            $hasAmbiguity = $hasAmbiguity || in_array(
                $object->writeState,
                [ObjectWriteState::Intent, ObjectWriteState::Unknown],
                true,
            );
            $hasInconsistency = $hasInconsistency || $object->classification === ObjectClassification::Inconsistent
                || $object->reconciliationResolutionInvalid;
        }

        $crossProjectionInvalid = $this->crossProjectionInvalid(
            $reconciliationResult,
            $item->reconciliation_event_id ?? null,
            $rows,
        );
        $hasInconsistency = $hasInconsistency || $crossProjectionInvalid;

        $functionalStorageSetExact = $this->functionalStorageSetExact(
            $item,
            $manifest,
            $rows,
            $objects,
            $itemConsistent && ! $reconciliationResultInvalid && ! $crossProjectionInvalid,
        );
        $exactSet = $functionalStorageSetExact && $this->hasNoCleanupHistory($objects);
        $classification = match (true) {
            $hasInconsistency => ItemClassification::InternallyInconsistent,
            $hasCleanupAttention => ItemClassification::CleanupAttentionCandidate,
            $exactSet => ItemClassification::StorageSetExactDomainRevalidationPending,
            $this->noPublicationObservedNow($manifest, $objects, $manifestReport, $hasAmbiguity) => ItemClassification::NoPublicationObservedNow,
            default => ItemClassification::AmbiguousBlocked,
        };

        return new ItemReconciliationReport(
            id: (int) ($item->id ?? 0),
            runId: (string) ($item->run_id ?? ''),
            domain: is_string($item->domain ?? null) ? $item->domain : 'invalid',
            entityId: (int) ($item->entity_id ?? 0),
            phase: $phase,
            applyResult: $result,
            preflightClassification: $preflight?->value ?? 'invalid',
            objects: $objects,
            durableCounts: $durableCounts,
            classificationCounts: $classificationCounts,
            manifest: $manifestReport,
            classification: $classification,
            domainRevalidationPending: $classification === ItemClassification::StorageSetExactDomainRevalidationPending,
            functionalStorageSetExact: $functionalStorageSetExact,
            reconciliationResult: $reconciliationResult,
            reconciliationResultInvalid: $reconciliationResultInvalid,
            hasReconciliationEvent: $reconciliationEvent->present,
            reconciliationEventFingerprint: $reconciliationEvent->fingerprint,
        );
    }

    /** @return array{?ResponsiveManifest, ?ItemPhase, ?ApplyResult, ?PreflightClassification, bool} */
    private function itemContext(stdClass $item, stdClass $run): array
    {
        [$selectedDomain, $selectedAfterId, , $selectedUpperBound, , $runConsistent] = $this->runSelection($run);
        [$manifest, $candidateConsistent] = $this->candidates->read($item);
        $phase = is_string($item->phase ?? null) ? ItemPhase::tryFrom($item->phase) : null;
        $result = is_string($item->apply_result ?? null) ? ApplyResult::tryFrom($item->apply_result) : null;
        $preflight = is_string($item->preflight_classification ?? null)
            ? PreflightClassification::tryFrom($item->preflight_classification)
            : null;
        $consistent = $candidateConsistent
            && $runConsistent
            && (string) ($item->run_id ?? '') === (string) ($run->run_id ?? '')
            && $this->canonicalUuid((string) ($item->run_id ?? ''))
            && is_string($item->domain ?? null) && $item->domain === $selectedDomain
            && (int) ($item->entity_id ?? 0) > $selectedAfterId
            && (int) ($item->entity_id ?? 0) <= $selectedUpperBound
            && $phase !== null && $preflight !== null
            && (($phase === ItemPhase::Finished && $result !== null && ($item->finished_at ?? null) !== null)
                || ($phase !== ItemPhase::Finished && $result === null && ($item->finished_at ?? null) === null));

        return [$manifest, $phase, $result, $preflight, $consistent];
    }

    private function objectReport(
        stdClass $object,
        stdClass $item,
        stdClass $run,
        ?ResponsiveManifest $manifest,
        bool $itemConsistent,
    ): ObjectReconciliationReport {
        [$descriptor, $planConsistent] = $this->plannedDescriptor($object, $item, $manifest);
        $parentConsistent = $itemConsistent
            && (int) ($object->item_id ?? 0) === (int) ($item->id ?? 0)
            && $planConsistent;
        $observation = $this->storageIdentityMatches($run) === true
            ? $this->observer->observe($object, $descriptor)
            : ObjectObservation::Unreadable;

        return $this->classifier->report($object, $observation, $parentConsistent,
            $this->objectLinkIsValid($object, $item, $run));
    }

    /** @return array{?ManifestImage, bool} */
    private function plannedDescriptor(stdClass $object, stdClass $item, ?ResponsiveManifest $manifest): array
    {
        if ($manifest === null || ! is_string($object->kind ?? null)) {
            return [null, false];
        }
        if ($object->kind === ObjectKind::Manifest->value) {
            return [null, is_string($object->object_key ?? null)
                && $object->object_key === ($item->manifest_key ?? null)
                && $object->expected_sha256 === ($item->candidate_manifest_sha256 ?? null)
                && (int) $object->expected_size === strlen((string) $item->candidate_manifest_json)
                && $object->mime_type === 'application/json'];
        }
        if ($object->kind !== ObjectKind::Variant->value) {
            return [null, false];
        }
        foreach ($manifest->variants as $descriptor) {
            if ($descriptor->key === ($object->object_key ?? null)) {
                return [$descriptor, (int) $object->expected_size === $descriptor->size
                    && $object->mime_type === $descriptor->mimeType];
            }
        }

        return [null, false];
    }

    private function itemLinkIsValid(stdClass $item, stdClass $run, ItemReconciliationResult $result): bool
    {
        return $this->events->projectionEvent(
            $item->reconciliation_event_id ?? null,
            $result === ItemReconciliationResult::ForwardAccepted
                ? ReconciliationEventType::ItemForwardAccepted
                : ReconciliationEventType::ItemNoEffectClosed,
            (string) ($item->run_id ?? ''),
            (int) ($item->id ?? 0),
            $run->storage_identity_hash ?? null,
        ) !== null;
    }

    private function objectLinkIsValid(stdClass $object, stdClass $item, stdClass $run): bool
    {
        if (($object->reconciliation_event_id ?? null) === null) {
            return true;
        }

        return $this->events->projectionEvent(
            $object->reconciliation_event_id,
            ReconciliationEventType::ItemForwardAccepted,
            (string) ($item->run_id ?? ''),
            (int) ($item->id ?? 0),
            $run->storage_identity_hash ?? null,
        ) !== null;
    }

    /**
     * Forward acceptance is item-atomic: item and every object must name the same exact event. A
     * no-effect closure never projects objects, and objects never resolve without their item.
     * This compares durable identifiers from the journal rows; the truncated fingerprints exposed
     * by the reports are presentation metadata and never establish durable equality.
     *
     * @param  list<stdClass>  $rows
     */
    private function crossProjectionInvalid(?ItemReconciliationResult $result, mixed $itemPointer, array $rows): bool
    {
        if ($result === ItemReconciliationResult::ForwardAccepted) {
            if ($rows === [] || ! is_string($itemPointer)) {
                return true;
            }
            foreach ($rows as $row) {
                $pointer = $row->reconciliation_event_id ?? null;
                if (($row->reconciliation_resolution ?? null) !== ObjectReconciliationResolution::ForwardRetained->value
                    || ! is_string($pointer) || $pointer !== $itemPointer) {
                    return true;
                }
            }

            return false;
        }

        foreach ($rows as $row) {
            if (($row->reconciliation_resolution ?? null) !== null || ($row->reconciliation_event_id ?? null) !== null) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  list<stdClass>  $rows
     * @param  list<ObjectReconciliationReport>  $objects
     */
    private function functionalStorageSetExact(
        stdClass $item,
        ?ResponsiveManifest $manifest,
        array $rows,
        array $objects,
        bool $parentsConsistent,
    ): bool {
        if (! $parentsConsistent || $manifest === null
            || count($rows) !== count($objects)
            || count($objects) !== count($manifest->variants) + 1
            || ! is_string($item->manifest_key ?? null)) {
            return false;
        }

        $expectedKeys = [$item->manifest_key, ...array_map(
            static fn (ManifestImage $variant): string => $variant->key,
            $manifest->variants,
        )];
        $actualKeys = [];
        foreach ($rows as $row) {
            if (! is_string($row->object_key ?? null)) {
                return false;
            }
            $actualKeys[] = $row->object_key;
        }
        sort($expectedKeys);
        sort($actualKeys);
        if ($actualKeys !== $expectedKeys || count(array_unique($actualKeys)) !== count($actualKeys)) {
            return false;
        }

        foreach ($objects as $object) {
            if ($object->observation !== ObjectObservation::ExpectedContentPresent
                || $object->cleanupState === CleanupState::Deleted
                || $object->preventsTrustworthyClassification()) {
                return false;
            }
        }

        return count(array_filter($objects, static fn (ObjectReconciliationReport $object): bool => $object->kind === ObjectKind::Manifest)) === 1;
    }

    /** @param list<ObjectReconciliationReport> $objects */
    private function hasNoCleanupHistory(array $objects): bool
    {
        foreach ($objects as $object) {
            if ($object->cleanupState !== CleanupState::NotRequired) {
                return false;
            }
        }

        return true;
    }

    /** @param list<ObjectReconciliationReport> $objects */
    private function noPublicationObservedNow(
        ?ResponsiveManifest $manifest,
        array $objects,
        ?ObjectReconciliationReport $manifestReport,
        bool $hasAmbiguity,
    ): bool {
        if ($manifest === null) {
            return $objects === [];
        }
        if ($manifestReport === null || $manifestReport->observation !== ObjectObservation::AbsentNow || $hasAmbiguity) {
            return false;
        }
        foreach ($objects as $object) {
            if (! in_array($object->classification, [
                ObjectClassification::ResolvedByJournal,
                ObjectClassification::Collision,
                ObjectClassification::KnownFailedWithoutWrite,
                ObjectClassification::CreatedMissingNow,
            ], true)) {
                return false;
            }
        }

        return true;
    }

    /** @return array{string, int, int, int, int, bool} */
    private function runSelection(stdClass $run): array
    {
        try {
            $options = json_decode($run->options_json, true, 4, JSON_THROW_ON_ERROR);
            $bounds = json_decode($run->upper_bounds_json, true, 4, JSON_THROW_ON_ERROR);
            $checkpoints = json_decode($run->checkpoints_json, true, 4, JSON_THROW_ON_ERROR);
            $domain = is_array($options) && is_string($options['domain'] ?? null)
                ? ManagedMediaDomain::tryFrom($options['domain'])
                : null;
            $state = is_string($run->state ?? null) ? RunState::tryFrom($run->state) : null;
            $reconciliationEvent = ReconciliationEventPointer::from($run->reconciliation_event_id ?? null);
            $valid = $this->canonicalUuid((string) ($run->run_id ?? ''))
                && ($run->mode ?? null) === JournalMode::Apply->value
                && $state !== null
                && is_array($options) && array_keys($options) === ['domain', 'after_id', 'limit']
                && $domain !== null && is_int($options['after_id']) && $options['after_id'] >= 0
                && is_int($options['limit']) && $options['limit'] >= 1 && $options['limit'] <= self::MAX_CHILDREN
                && is_array($bounds) && array_keys($bounds) === [$domain->value]
                && is_int($bounds[$domain->value]) && $bounds[$domain->value] >= 0
                && is_array($checkpoints) && array_keys($checkpoints) === [$domain->value]
                && is_int($checkpoints[$domain->value]) && $checkpoints[$domain->value] >= $options['after_id']
                && ($checkpoints[$domain->value] <= $bounds[$domain->value]
                    || $checkpoints[$domain->value] === $options['after_id'])
                && is_string($run->storage_identity_hash ?? null)
                && preg_match('/\A[0-9a-f]{64}\z/D', $run->storage_identity_hash) === 1
                && $reconciliationEvent->valid
                && (($state === RunState::Active && ($run->finished_at ?? null) === null)
                    || ($state !== RunState::Active && ($run->finished_at ?? null) !== null));

            return [
                $domain?->value ?? 'invalid',
                is_int($options['after_id'] ?? null) ? $options['after_id'] : 0,
                is_int($options['limit'] ?? null) ? $options['limit'] : 0,
                $domain !== null && is_int($bounds[$domain->value] ?? null) ? $bounds[$domain->value] : 0,
                $domain !== null && is_int($checkpoints[$domain->value] ?? null) ? $checkpoints[$domain->value] : 0,
                $valid,
            ];
        } catch (Throwable) {
            return ['invalid', 0, 0, 0, 0, false];
        }
    }

    private function storageIdentityMatches(stdClass $run): ?bool
    {
        if (! is_string($run->storage_identity_hash ?? null)
            || preg_match('/\A[0-9a-f]{64}\z/D', $run->storage_identity_hash) !== 1) {
            return null;
        }
        try {
            return hash_equals($run->storage_identity_hash, StorageIdentity::current($this->database)->hash);
        } catch (Throwable) {
            return null;
        }
    }

    private function canonicalUuid(string $value): bool
    {
        return preg_match('/\A[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}\z/D', $value) === 1;
    }
}
