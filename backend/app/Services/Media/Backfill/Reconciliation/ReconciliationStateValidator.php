<?php

namespace App\Services\Media\Backfill\Reconciliation;

use App\Services\Media\Backfill\ManagedMediaDomain;
use App\Services\Media\Backfill\PreflightClassification;
use App\Services\Media\Backfill\Safety\ApplyResult;
use App\Services\Media\Backfill\Safety\CleanupState;
use App\Services\Media\Backfill\Safety\CreateState;
use App\Services\Media\Backfill\Safety\ItemPhase;
use App\Services\Media\Backfill\Safety\JournalMode;
use App\Services\Media\Backfill\Safety\ObjectKind;
use App\Services\Media\Backfill\Safety\ObjectWriteState;
use App\Services\Media\Backfill\Safety\RunState;
use App\Services\Media\Backfill\Safety\TargetObject;
use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Database\Connection;
use Illuminate\Database\DatabaseManager;
use stdClass;
use Throwable;

/**
 * DB-only semantic view of durable reconciliation state. It never observes or mutates storage and
 * is the single B3 policy used by the recovery barrier, late run closure and APPLY freeze guards.
 */
final class ReconciliationStateValidator
{
    private const SNAPSHOT_VERSION = 1;

    private const MAX_ITEMS = 1000;

    public function __construct(
        private readonly DatabaseManager $database,
        private readonly CandidateManifestReader $candidates,
        private readonly ObjectEvidenceClassifier $classifier,
        private readonly ReconciliationEventValidator $events,
    ) {}

    public function hasRunFootprint(string $runId): bool
    {
        return $this->hasRunFootprintOn($this->database->connection(), $runId);
    }

    /** Any event or projection freezes the exact historical APPLY run. */
    public function hasRunFootprintOn(Connection $db, string $runId): bool
    {
        return $db->table('media_backfill_reconciliation_events')->useWritePdo()
            ->where('run_id', $runId)->exists()
            || $db->table('media_backfill_runs')->useWritePdo()->where('run_id', $runId)
                ->whereNotNull('reconciliation_event_id')->exists()
            || $db->table('media_backfill_items')->useWritePdo()->where('run_id', $runId)
                ->where(fn ($query) => $query->whereNotNull('reconciliation_result')
                    ->orWhereNotNull('reconciliation_event_id'))->exists()
            || $db->table('media_backfill_objects as objects')->useWritePdo()
                ->join('media_backfill_items as items', 'items.id', '=', 'objects.item_id')
                ->where('items.run_id', $runId)
                ->where(fn ($query) => $query->whereNotNull('objects.reconciliation_resolution')
                    ->orWhereNotNull('objects.reconciliation_event_id'))->exists();
    }

    /** Global Barrier V2. Invalid or unsupported durable state is blocking. */
    public function barrierIsClear(Connection $db): bool
    {
        $runs = $db->table('media_backfill_runs')->useWritePdo()->orderBy('run_id')->get()->all();
        $seenItems = 0;
        $seenObjects = 0;
        foreach ($runs as $run) {
            $state = is_string($run->state ?? null) ? RunState::tryFrom($run->state) : null;
            if ($state === RunState::Active) {
                return false;
            }
            [$items, $objects] = $this->descendants($db, (string) ($run->run_id ?? ''));
            $seenItems += count($items);
            $seenObjects += count($objects);
            $closure = null;
            $start = null;
            if (($run->reconciliation_event_id ?? null) !== null) {
                $closure = $this->events->projectionEvent(
                    $run->reconciliation_event_id,
                    ReconciliationEventType::RunClosedAfterReconciliation,
                    (string) ($run->run_id ?? ''),
                    null,
                    $run->storage_identity_hash ?? null,
                );
                if ($closure === null) {
                    return false;
                }
                $start = $this->events->attemptStart(
                    $closure->attempt_id ?? null,
                    (string) ($run->run_id ?? ''),
                    $run->storage_identity_hash ?? null,
                );
                if ($start === null) {
                    return false;
                }
            }
            $analysis = $this->evaluate($run, $items, $objects, $start);
            if ($analysis === null || ! $analysis['resolved']
                || ($closure !== null && ! $this->closureProjectionIsValid($run, $closure, $analysis))) {
                return false;
            }
        }

        return $seenItems === $db->table('media_backfill_items')->useWritePdo()->count()
            && $seenObjects === $db->table('media_backfill_objects')->useWritePdo()->count();
    }

    /** Full DB-only validation of the run-level closure projection used by read-only inspection. */
    public function runClosureProjectionIsValid(stdClass $run): bool
    {
        $event = $this->events->projectionEvent(
            $run->reconciliation_event_id ?? null,
            ReconciliationEventType::RunClosedAfterReconciliation,
            (string) ($run->run_id ?? ''),
            null,
            $run->storage_identity_hash ?? null,
        );
        if ($event === null) {
            return false;
        }
        $start = $this->events->attemptStart(
            $event->attempt_id ?? null,
            (string) ($run->run_id ?? ''),
            $run->storage_identity_hash ?? null,
        );
        if ($start === null) {
            return false;
        }
        [$items, $objects] = $this->descendants($this->database->connection(), (string) $run->run_id);
        $analysis = $this->evaluate($run, $items, $objects, $start);

        return $analysis !== null && $analysis['resolved']
            && $this->closureProjectionIsValid($run, $event, $analysis);
    }

    /**
     * DB-only operational view of one item. It deliberately reuses itemState(), the exact
     * projection/event policy used by Barrier V2 and run closure.
     *
     * @param  list<stdClass>  $objects
     */
    public function analyzeItem(
        stdClass $run,
        stdClass $item,
        array $objects,
    ): ReconciliationItemOperationalAnalysis {
        $runId = (string) ($run->run_id ?? '');
        $itemId = (int) ($item->id ?? 0);
        if (! $this->canonicalUuid($runId) || $itemId < 1 || ! array_is_list($objects)
            || count($objects) > self::MAX_ITEMS) {
            return ReconciliationItemOperationalAnalysis::inconsistent();
        }
        foreach ($objects as $object) {
            if (! $object instanceof stdClass) {
                return ReconciliationItemOperationalAnalysis::inconsistent();
            }
        }
        try {
            $db = $this->database->connection();
            $durableRun = $db->table('media_backfill_runs')->useWritePdo()->where('run_id', $runId)->first();
            $durableItem = $db->table('media_backfill_items')->useWritePdo()->where('id', $itemId)->first();
            $durableObjects = $db->table('media_backfill_objects')->useWritePdo()->where('item_id', $itemId)
                ->orderBy('id')->limit(self::MAX_ITEMS + 1)->get()->all();
        } catch (Throwable) {
            return ReconciliationItemOperationalAnalysis::inconsistent();
        }
        if (! $durableRun instanceof stdClass || ! $durableItem instanceof stdClass
            || count($durableObjects) > self::MAX_ITEMS
            || (array) $durableRun !== (array) $run || (array) $durableItem !== (array) $item
            || array_map(static fn (stdClass $row): array => (array) $row, $durableObjects)
                !== array_map(static fn (stdClass $row): array => (array) $row, $objects)) {
            return ReconciliationItemOperationalAnalysis::inconsistent();
        }

        $selection = $this->runSelection($run);
        $entityId = (int) ($item->entity_id ?? 0);
        if ($selection === null || $itemId < 1 || $entityId <= $selection['after_id']
            || $entityId > $selection['upper_bound']
            || (string) ($item->run_id ?? '') !== (string) ($run->run_id ?? '')
            || ($item->domain ?? null) !== $selection['domain']->value) {
            return ReconciliationItemOperationalAnalysis::inconsistent();
        }

        $previousObjectId = 0;
        foreach ($objects as $object) {
            $objectId = (int) ($object->id ?? 0);
            if ($objectId <= $previousObjectId || (int) ($object->item_id ?? 0) !== $itemId) {
                return ReconciliationItemOperationalAnalysis::inconsistent();
            }
            $previousObjectId = $objectId;
        }

        $referencedEvents = [];
        try {
            $semantic = $this->itemState($run, $item, $objects, $referencedEvents);
        } catch (Throwable) {
            return ReconciliationItemOperationalAnalysis::inconsistent();
        }
        if ($semantic === null
            || ($entityId <= $selection['checkpoint'] && $semantic['phase'] !== ItemPhase::Finished)) {
            return ReconciliationItemOperationalAnalysis::inconsistent();
        }

        $ambiguousWriteObjectIds = [];
        $cleanupBlockerObjectIds = [];
        $hasDeletedCleanup = false;
        foreach ($objects as $object) {
            $write = ObjectWriteState::tryFrom((string) ($object->write_state ?? ''));
            $cleanup = CleanupState::tryFrom((string) ($object->cleanup_state ?? ''));
            if ($write === null || $cleanup === null) {
                return ReconciliationItemOperationalAnalysis::inconsistent();
            }
            if (in_array($write, [ObjectWriteState::Intent, ObjectWriteState::Unknown], true)) {
                $ambiguousWriteObjectIds[] = (int) $object->id;
            }
            if (in_array($cleanup, [CleanupState::Pending, CleanupState::Failed, CleanupState::Unknown], true)) {
                $cleanupBlockerObjectIds[] = (int) $object->id;
            }
            $hasDeletedCleanup = $hasDeletedCleanup || $cleanup === CleanupState::Deleted;
        }

        $unfinished = $semantic['phase'] !== ItemPhase::Finished;
        $noEffectEligible = $this->noEffectHistoryIsEligible($item, $objects);
        $resolution = is_string($item->reconciliation_result ?? null)
            ? ItemReconciliationResult::tryFrom($item->reconciliation_result)
            : null;
        if ($semantic['resolved']) {
            $state = $resolution === ItemReconciliationResult::ForwardAccepted
                ? ReconciliationItemOperationalState::ResolvedForward
                : ReconciliationItemOperationalState::ResolvedNoEffect;
        } elseif (! $unfinished && $ambiguousWriteObjectIds === []) {
            $state = ReconciliationItemOperationalState::OrdinaryNonBlocking;
        } elseif ($unfinished && $noEffectEligible) {
            $state = ReconciliationItemOperationalState::NoEffectCandidate;
        } elseif ($objects !== [] && ! $hasDeletedCleanup && $this->objectPlanIsValid(
            $item,
            PreflightClassification::LegacyBackfillable,
            $objects,
            true,
        )) {
            $state = ReconciliationItemOperationalState::ForwardCandidate;
        } else {
            $state = ReconciliationItemOperationalState::UnresolvedBlocked;
        }

        return new ReconciliationItemOperationalAnalysis(
            $state,
            $unfinished,
            $ambiguousWriteObjectIds,
            $cleanupBlockerObjectIds,
            $noEffectEligible,
            $state === ReconciliationItemOperationalState::ForwardCandidate,
        );
    }

    /**
     * @param  list<stdClass>  $items
     * @param  list<stdClass>  $objects
     * @return null|array{items_total: int, objects_total: int, item_blockers_resolved: int,
     *     write_blockers_resolved: int, cleanup_blockers_remaining: int,
     *     resolution_snapshot_sha256: string, resolved: bool}
     */
    public function evaluate(stdClass $run, array $items, array $objects, ?stdClass $closureStart = null): ?array
    {
        $selection = $this->runSelection($run);
        if ($selection === null || count($items) > $selection['limit']) {
            return null;
        }
        $runId = (string) $run->run_id;
        $identity = (string) $run->storage_identity_hash;
        $byItem = [];
        $previousObject = 0;
        foreach ($objects as $object) {
            $id = (int) ($object->id ?? 0);
            $itemId = (int) ($object->item_id ?? 0);
            if ($id <= $previousObject || $itemId < 1) {
                return null;
            }
            $previousObject = $id;
            $byItem[$itemId][] = $object;
        }

        $events = [];
        if ($closureStart !== null && ! $this->addEvent($events, $closureStart, $runId, $identity)) {
            return null;
        }
        $itemSnapshots = [];
        $previousItem = 0;
        $previousEntity = $selection['after_id'];
        $checkpointFound = $selection['checkpoint'] === $selection['after_id'];
        $itemBlockers = 0;
        $writeBlockers = 0;
        $cleanupBlockers = 0;
        foreach ($items as $item) {
            $itemId = (int) ($item->id ?? 0);
            $entityId = (int) ($item->entity_id ?? 0);
            if ($itemId <= $previousItem || $entityId <= $previousEntity
                || (string) ($item->run_id ?? '') !== $runId
                || ($item->domain ?? null) !== $selection['domain']->value
                || $entityId > $selection['upper_bound']) {
                return null;
            }
            $previousItem = $itemId;
            $previousEntity = $entityId;
            $itemObjects = $byItem[$itemId] ?? [];
            unset($byItem[$itemId]);
            $itemState = $this->itemState($run, $item, $itemObjects, $events);
            if ($itemState === null) {
                return null;
            }
            if ($entityId <= $selection['checkpoint'] && $itemState['phase'] !== ItemPhase::Finished) {
                return null;
            }
            if ($entityId === $selection['checkpoint']) {
                $checkpointFound = true;
            }
            if ($itemState['phase'] !== ItemPhase::Finished && $itemState['resolved']) {
                $itemBlockers++;
            }
            foreach ($itemObjects as $object) {
                $write = ObjectWriteState::from($object->write_state);
                $cleanup = CleanupState::from($object->cleanup_state);
                if (in_array($write, [ObjectWriteState::Intent, ObjectWriteState::Unknown], true)
                    && $itemState['forward']) {
                    $writeBlockers++;
                }
                if (in_array($cleanup, [CleanupState::Pending, CleanupState::Failed, CleanupState::Unknown], true)) {
                    $cleanupBlockers++;
                }
            }
            $itemSnapshots[] = $this->itemSnapshot($item, $itemObjects);
        }
        if ($byItem !== [] || ! $checkpointFound) {
            return null;
        }

        ksort($events, SORT_STRING);
        $preimage = [
            'v' => self::SNAPSHOT_VERSION,
            'run' => [
                'run_id' => $runId,
                'mode' => (string) $run->mode,
                'options_json' => (string) $run->options_json,
                'storage_identity_hash' => $identity,
                'code_revision' => $run->code_revision === null ? null : (string) $run->code_revision,
                'upper_bounds_json' => (string) $run->upper_bounds_json,
                'checkpoints_json' => (string) $run->checkpoints_json,
            ],
            'items' => $itemSnapshots,
            'events' => array_values($events),
        ];
        try {
            $json = json_encode($preimage, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        } catch (Throwable) {
            return null;
        }

        $unfinished = 0;
        $ambiguous = 0;
        foreach ($items as $item) {
            if ($item->phase !== ItemPhase::Finished->value) {
                $unfinished++;
            }
        }
        foreach ($objects as $object) {
            if (in_array($object->write_state, [ObjectWriteState::Intent->value, ObjectWriteState::Unknown->value], true)) {
                $ambiguous++;
            }
        }

        return [
            'items_total' => count($items),
            'objects_total' => count($objects),
            'item_blockers_resolved' => $itemBlockers,
            'write_blockers_resolved' => $writeBlockers,
            'cleanup_blockers_remaining' => $cleanupBlockers,
            'resolution_snapshot_sha256' => hash('sha256', $json),
            'resolved' => $unfinished === $itemBlockers && $ambiguous === $writeBlockers && $cleanupBlockers === 0,
        ];
    }

    /** @param array<string, mixed> $analysis */
    public function closureProjectionIsValid(stdClass $run, stdClass $event, array $analysis): bool
    {
        try {
            $evidence = json_decode((string) $event->evidence_json, true, 8, JSON_THROW_ON_ERROR);
        } catch (Throwable) {
            return false;
        }
        $expected = [
            'v' => ReconciliationEventValidator::EVIDENCE_VERSION,
            'kind' => ReconciliationEventType::RunClosedAfterReconciliation->value,
            'observed_at' => $evidence['observed_at'] ?? null,
            'storage_identity_hash' => (string) $run->storage_identity_hash,
            'backend_mode' => (string) $event->backend_mode,
            'from_state' => RunState::Active->value,
            'to_state' => RunState::Interrupted->value,
            'items_total' => $analysis['items_total'],
            'objects_total' => $analysis['objects_total'],
            'item_blockers_resolved' => $analysis['item_blockers_resolved'],
            'write_blockers_resolved' => $analysis['write_blockers_resolved'],
            'cleanup_blockers_remaining' => $analysis['cleanup_blockers_remaining'],
            'resolution_snapshot_sha256' => $analysis['resolution_snapshot_sha256'],
        ];
        $timestamp = $this->databaseTimestamp($evidence['observed_at'] ?? null);

        return $evidence === $expected
            && ($run->state ?? null) === RunState::Interrupted->value
            && ($run->reconciliation_event_id ?? null) === ($event->event_id ?? null)
            && $timestamp !== null
            && (string) ($event->created_at ?? '') === $timestamp
            && (string) ($run->finished_at ?? '') === $timestamp
            && (string) ($run->updated_at ?? '') === $timestamp;
    }

    /** @return array{list<stdClass>, list<stdClass>} */
    private function descendants(Connection $db, string $runId): array
    {
        $items = $db->table('media_backfill_items')->useWritePdo()->where('run_id', $runId)
            ->orderBy('id')->get()->all();
        $objects = $db->table('media_backfill_objects as objects')->useWritePdo()
            ->join('media_backfill_items as items', 'items.id', '=', 'objects.item_id')
            ->where('items.run_id', $runId)->select('objects.*')->orderBy('objects.id')->get()->all();

        return [$items, $objects];
    }

    /** @return null|array{domain: ManagedMediaDomain, after_id: int, limit: int, upper_bound: int, checkpoint: int} */
    private function runSelection(stdClass $run): ?array
    {
        $runId = $run->run_id ?? null;
        $revision = $run->code_revision ?? null;
        $state = is_string($run->state ?? null) ? RunState::tryFrom($run->state) : null;
        if (! is_string($runId) || ! $this->canonicalUuid($runId)
            || ($run->mode ?? null) !== JournalMode::Apply->value || $state === null
            || ($state === RunState::Active) !== (($run->finished_at ?? null) === null)
            || ! is_string($run->storage_identity_hash ?? null) || ! $this->sha256($run->storage_identity_hash)
            || ($revision !== null && (! is_string($revision) || preg_match('/\A[0-9a-f]{40}\z/D', $revision) !== 1))) {
            return null;
        }
        try {
            $options = json_decode((string) $run->options_json, true, 4, JSON_THROW_ON_ERROR);
            $bounds = json_decode((string) $run->upper_bounds_json, true, 4, JSON_THROW_ON_ERROR);
            $checkpoints = json_decode((string) $run->checkpoints_json, true, 4, JSON_THROW_ON_ERROR);
        } catch (Throwable) {
            return null;
        }
        $domain = is_array($options) && is_string($options['domain'] ?? null)
            ? ManagedMediaDomain::tryFrom($options['domain'])
            : null;
        if ($domain === null || array_keys($options) !== ['domain', 'after_id', 'limit']
            || ! is_int($options['after_id']) || $options['after_id'] < 0
            || ! is_int($options['limit']) || $options['limit'] < 1 || $options['limit'] > self::MAX_ITEMS
            || ! is_array($bounds) || array_keys($bounds) !== [$domain->value]
            || ! is_int($bounds[$domain->value]) || $bounds[$domain->value] < 0
            || ! is_array($checkpoints) || array_keys($checkpoints) !== [$domain->value]
            || ! is_int($checkpoints[$domain->value]) || $checkpoints[$domain->value] < $options['after_id']
            || ($checkpoints[$domain->value] > $bounds[$domain->value]
                && $checkpoints[$domain->value] !== $options['after_id'])) {
            return null;
        }

        return [
            'domain' => $domain,
            'after_id' => $options['after_id'],
            'limit' => $options['limit'],
            'upper_bound' => $bounds[$domain->value],
            'checkpoint' => $checkpoints[$domain->value],
        ];
    }

    /**
     * @param  list<stdClass>  $objects
     * @param  array<string, array<string, mixed>>  $events
     * @return null|array{phase: ItemPhase, resolved: bool, forward: bool}
     */
    private function itemState(stdClass $run, stdClass $item, array $objects, array &$events): ?array
    {
        $phase = is_string($item->phase ?? null) ? ItemPhase::tryFrom($item->phase) : null;
        $result = is_string($item->apply_result ?? null) ? ApplyResult::tryFrom($item->apply_result) : null;
        $preflight = is_string($item->preflight_classification ?? null)
            ? PreflightClassification::tryFrom($item->preflight_classification)
            : null;
        if ($phase === null || $preflight === null || ($item->apply_result ?? null) !== $result?->value
            || ($phase === ItemPhase::Finished) !== ($result !== null)
            || ($result !== null) !== (($item->finished_at ?? null) !== null)
            || ! $this->candidateMetadataIsValid($item, $preflight, $phase)
            || ! $this->applyResultIsCoherent($phase, $result, $objects)
            || ! $this->objectPlanIsValid($item, $preflight, $objects,
                $result === ApplyResult::Published)) {
            return null;
        }
        $itemProjection = $this->projection(
            $item->reconciliation_result ?? null,
            $item->reconciliation_event_id ?? null,
            ItemReconciliationResult::class,
        );
        if ($itemProjection === null) {
            return null;
        }
        [$resolution, $pointer] = $itemProjection;
        foreach ($objects as $object) {
            if ($this->classifier->attributionFor($object) === ObjectAttribution::InconsistentJournal
                || $this->projection($object->reconciliation_resolution ?? null,
                    $object->reconciliation_event_id ?? null, ObjectReconciliationResolution::class) === null) {
                return null;
            }
        }
        if ($resolution === null) {
            foreach ($objects as $object) {
                if (($object->reconciliation_resolution ?? null) !== null
                    || ($object->reconciliation_event_id ?? null) !== null) {
                    return null;
                }
            }

            return ['phase' => $phase, 'resolved' => false, 'forward' => false];
        }

        $type = $resolution === ItemReconciliationResult::ForwardAccepted
            ? ReconciliationEventType::ItemForwardAccepted
            : ReconciliationEventType::ItemNoEffectClosed;
        $event = $this->events->projectionEvent(
            $pointer,
            $type,
            (string) $run->run_id,
            (int) $item->id,
            $run->storage_identity_hash ?? null,
        );
        if ($event === null || ! $this->addEvent($events, $event, (string) $run->run_id, (string) $run->storage_identity_hash)) {
            return null;
        }
        $start = $this->events->attemptStart($event->attempt_id, (string) $run->run_id, $run->storage_identity_hash);
        if ($start === null || ! $this->addEvent($events, $start, (string) $run->run_id, (string) $run->storage_identity_hash)) {
            return null;
        }
        $valid = $resolution === ItemReconciliationResult::ForwardAccepted
            ? $this->forwardIsValid($item, $objects, $event, $pointer)
            : $this->noEffectIsValid($item, $objects, $event);

        return $valid ? [
            'phase' => $phase,
            'resolved' => true,
            'forward' => $resolution === ItemReconciliationResult::ForwardAccepted,
        ] : null;
    }

    private function candidateMetadataIsValid(
        stdClass $item,
        PreflightClassification $preflight,
        ItemPhase $phase,
    ): bool {
        $master = $item->master_key ?? null;
        if (! is_string($item->master_key_hash ?? null) || ! $this->sha256($item->master_key_hash)
            || ($master === null) !== (($item->manifest_key ?? null) === null)) {
            return false;
        }
        if ($preflight === PreflightClassification::LegacyBackfillable) {
            [$manifest, $coherent] = $this->candidates->read($item);

            return $coherent && $manifest !== null && is_string($item->source_sha256 ?? null)
                && $this->sha256($item->source_sha256)
                && ($phase === ItemPhase::Inspected ? ($item->revalidated_at ?? null) === null : true)
                && (in_array($phase, [ItemPhase::Revalidated, ItemPhase::Writing], true)
                    ? ($item->revalidated_at ?? null) !== null : true);
        }

        return ($item->candidate_manifest_json ?? null) === null
            && ($item->candidate_manifest_sha256 ?? null) === null
            && ($item->source_sha256 ?? null) === null
            && in_array($phase, [ItemPhase::Inspected, ItemPhase::Finished], true)
            && ($item->revalidated_at ?? null) === null
            && (! is_string($master) || hash_equals(hash('sha256', $master), $item->master_key_hash));
    }

    /** @param list<stdClass> $objects */
    private function applyResultIsCoherent(ItemPhase $phase, ?ApplyResult $result, array $objects): bool
    {
        if ($phase === ItemPhase::Writing) {
            foreach ($objects as $object) {
                if (($object->write_state ?? null) !== ObjectWriteState::Planned->value) {
                    return true;
                }
            }

            return false;
        }
        if ($result === null) {
            return true;
        }
        $ambiguous = array_filter($objects, static fn (stdClass $object): bool => in_array(
            $object->write_state ?? null,
            [ObjectWriteState::Intent->value, ObjectWriteState::Unknown->value],
            true,
        ));
        $created = array_filter($objects, static fn (stdClass $object): bool => ($object->write_state ?? null) === ObjectWriteState::Created->value);
        $dirty = array_filter($created, static fn (stdClass $object): bool => ($object->cleanup_state ?? null) !== CleanupState::Deleted->value);

        return match ($result) {
            ApplyResult::Published => $objects !== [] && count($objects) === count($created)
                && count($objects) === count(array_filter($created, static fn (stdClass $object): bool => $object->cleanup_state === CleanupState::NotRequired->value))
                && count(array_filter($created, static fn (stdClass $object): bool => $object->kind === ObjectKind::Manifest->value)) === 1,
            ApplyResult::PublicationUnknown => $ambiguous !== [],
            ApplyResult::FailedCompensated => $ambiguous === [] && $created !== [] && $dirty === [],
            ApplyResult::FailedCleanupIncomplete => $ambiguous === [] && $dirty !== []
                && count($dirty) === count(array_filter($dirty, static fn (stdClass $object): bool => in_array(
                    $object->cleanup_state,
                    [CleanupState::Pending->value, CleanupState::Failed->value, CleanupState::Unknown->value],
                    true,
                ))),
            ApplyResult::CollisionDetected => $ambiguous === [] && $dirty === []
                && array_filter($objects, static fn (stdClass $object): bool => $object->write_state === ObjectWriteState::Rejected->value
                    && $object->create_state === CreateState::Rejected->value) !== [],
            ApplyResult::Skipped, ApplyResult::ReferenceChanged => count($objects) === count(array_filter(
                $objects,
                static fn (stdClass $object): bool => $object->write_state === ObjectWriteState::Planned->value,
            )),
            ApplyResult::FailedNoWrites => $ambiguous === [] && $created === [],
        };
    }

    /** @param list<stdClass> $objects */
    private function objectPlanIsValid(
        stdClass $item,
        PreflightClassification $preflight,
        array $objects,
        bool $requireComplete,
    ): bool {
        if ($preflight !== PreflightClassification::LegacyBackfillable) {
            return $objects === [];
        }
        [$manifest, $coherent] = $this->candidates->read($item);
        if (! $coherent || $manifest === null) {
            return false;
        }
        $expected = [(string) $item->manifest_key => null];
        foreach ($manifest->variants as $descriptor) {
            $expected[$descriptor->key] = $descriptor;
        }
        foreach ($objects as $object) {
            $key = is_string($object->object_key ?? null) ? $object->object_key : '';
            if (! array_key_exists($key, $expected)) {
                return false;
            }
            $descriptor = $expected[$key];
            unset($expected[$key]);
            $valid = $descriptor === null
                ? ($object->kind ?? null) === ObjectKind::Manifest->value
                    && hash_equals((string) ($object->expected_sha256 ?? ''), (string) $item->candidate_manifest_sha256)
                    && (int) ($object->expected_size ?? 0) === strlen((string) $item->candidate_manifest_json)
                    && ($object->mime_type ?? null) === 'application/json'
                : ($object->kind ?? null) === ObjectKind::Variant->value
                    && (int) ($object->expected_size ?? 0) === $descriptor->size
                    && ($object->mime_type ?? null) === $descriptor->mimeType;
            try {
                $kind = is_string($object->kind ?? null) ? ObjectKind::tryFrom($object->kind) : null;
                if ($kind === null) {
                    return false;
                }
                new TargetObject(
                    $key,
                    $kind,
                    (string) ($object->expected_sha256 ?? ''),
                    (int) ($object->expected_size ?? 0),
                    (string) ($object->mime_type ?? ''),
                );
            } catch (Throwable) {
                return false;
            }
            if (! $valid) {
                return false;
            }
        }

        return ! $requireComplete || $expected === [];
    }

    /** @param list<stdClass> $objects */
    private function forwardIsValid(stdClass $item, array $objects, stdClass $event, string $pointer): bool
    {
        if ($objects === [] || ! $this->objectPlanIsValid(
            $item,
            PreflightClassification::LegacyBackfillable,
            $objects,
            true,
        )) {
            return false;
        }
        foreach ($objects as $object) {
            if (($object->reconciliation_resolution ?? null) !== ObjectReconciliationResolution::ForwardRetained->value
                || ($object->reconciliation_event_id ?? null) !== $pointer
                || CleanupState::tryFrom((string) ($object->cleanup_state ?? '')) === CleanupState::Deleted) {
                return false;
            }
        }
        try {
            $evidence = json_decode((string) $event->evidence_json, true, 8, JSON_THROW_ON_ERROR);
        } catch (Throwable) {
            return false;
        }
        $master = (string) $item->master_key;
        $reference = substr($master, 0, (int) strrpos($master, '.'));
        $expected = [
            'v' => ReconciliationEventValidator::EVIDENCE_VERSION,
            'kind' => ReconciliationEventType::ItemForwardAccepted->value,
            'observed_at' => $evidence['observed_at'] ?? null,
            'storage_identity_hash' => (string) $event->storage_identity_hash,
            'backend_mode' => (string) $event->backend_mode,
            'reference_identity_sha256' => hash('sha256', $reference),
            'master_key_sha256' => (string) $item->master_key_hash,
            'master_sha256' => (string) $item->source_sha256,
            'candidate_manifest_sha256' => (string) $item->candidate_manifest_sha256,
            'unique_live_owner_revalidated' => true,
            'live_owner_domain' => (string) $item->domain,
            'live_owner_entity_id' => (int) $item->entity_id,
            'manifest_structure_validated' => true,
            'no_unexpected_canonical_target' => true,
            'objects' => array_map(static fn (stdClass $object): array => [
                'object_id' => (int) $object->id,
                'observed_sha256' => (string) $object->expected_sha256,
                'observed_size' => (int) $object->expected_size,
                'observed_mime_type' => (string) $object->mime_type,
                'descriptor_validated' => true,
                'structure_validated' => true,
            ], $objects),
        ];

        return $evidence === $expected;
    }

    /** @param list<stdClass> $objects */
    private function noEffectIsValid(stdClass $item, array $objects, stdClass $event): bool
    {
        if (! $this->noEffectHistoryIsEligible($item, $objects)) {
            return false;
        }
        $facts = [];
        foreach ($objects as $object) {
            if (($object->reconciliation_resolution ?? null) !== null
                || ($object->reconciliation_event_id ?? null) !== null) {
                return false;
            }
            $attribution = $this->classifier->attributionFor($object);
            $facts[] = [
                'object_id' => (int) $object->id,
                'write_state' => (string) $object->write_state,
                'create_state' => $object->create_state === null ? null : (string) $object->create_state,
                'cleanup_state' => (string) $object->cleanup_state,
                'attribution' => $attribution->value,
            ];
        }
        try {
            $evidence = json_decode((string) $event->evidence_json, true, 8, JSON_THROW_ON_ERROR);
        } catch (Throwable) {
            return false;
        }
        $expected = [
            'v' => ReconciliationEventValidator::EVIDENCE_VERSION,
            'kind' => ReconciliationEventType::ItemNoEffectClosed->value,
            'observed_at' => $evidence['observed_at'] ?? null,
            'storage_identity_hash' => (string) $event->storage_identity_hash,
            'backend_mode' => (string) $event->backend_mode,
            'objects' => $facts,
        ];

        return $evidence === $expected;
    }

    /** Immutable APPLY facts only. No domain or storage observation is consulted. @param list<stdClass> $objects */
    private function noEffectHistoryIsEligible(stdClass $item, array $objects): bool
    {
        if (($item->phase ?? null) === ItemPhase::Finished->value
            || ($item->apply_result ?? null) !== null || ($item->finished_at ?? null) !== null) {
            return false;
        }
        foreach ($objects as $object) {
            $attribution = $this->classifier->attributionFor($object);
            $write = ObjectWriteState::tryFrom((string) ($object->write_state ?? ''));
            $create = is_string($object->create_state ?? null) ? CreateState::tryFrom($object->create_state) : null;
            if (! in_array($attribution, [ObjectAttribution::NotDispatched,
                ObjectAttribution::RejectedCollision, ObjectAttribution::FailedWithoutWrite], true)
                || ! in_array($write, [ObjectWriteState::Planned, ObjectWriteState::Rejected], true)
                || ! in_array($create, [null, CreateState::Rejected, CreateState::Failed], true)
                || ($object->cleanup_state ?? null) !== CleanupState::NotRequired->value
                || ($object->etag ?? null) !== null || ($object->version_id ?? null) !== null
                || ($object->write_confirmed_at ?? null) !== null
                || ($object->cleanup_attempted_at ?? null) !== null
                || ($object->cleanup_finished_at ?? null) !== null) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param  class-string<ItemReconciliationResult|ObjectReconciliationResolution>  $enum
     * @return null|array{null|ItemReconciliationResult|ObjectReconciliationResolution, ?string}
     */
    private function projection(mixed $value, mixed $pointer, string $enum): ?array
    {
        if ($value === null && $pointer === null) {
            return [null, null];
        }
        if (! is_string($value) || ! is_string($pointer) || ! $this->canonicalUuid($pointer)) {
            return null;
        }
        $resolution = $enum::tryFrom($value);

        return $resolution === null ? null : [$resolution, $pointer];
    }

    /** @param array<string, array<string, mixed>> $events */
    private function addEvent(array &$events, stdClass $event, string $runId, string $identity): bool
    {
        if (! $this->events->isStructurallyValid($event)
            || (string) ($event->run_id ?? '') !== $runId
            || ! is_string($event->storage_identity_hash ?? null)
            || ! hash_equals($identity, $event->storage_identity_hash)) {
            return false;
        }
        $id = (string) $event->event_id;
        $events[$id] = [
            'event_id' => $id,
            'event_type' => (string) $event->event_type,
            'run_id' => (string) $event->run_id,
            'item_id' => $event->item_id === null ? null : (int) $event->item_id,
            'attempt_id' => (string) $event->attempt_id,
            'storage_identity_hash' => (string) $event->storage_identity_hash,
            'backend_mode' => (string) $event->backend_mode,
            'code_revision' => $event->code_revision === null ? null : (string) $event->code_revision,
            'evidence_sha256' => (string) $event->evidence_sha256,
        ];

        return true;
    }

    /** @param list<stdClass> $objects @return array<string, mixed> */
    private function itemSnapshot(stdClass $item, array $objects): array
    {
        return [
            'id' => (int) $item->id,
            'run_id' => (string) $item->run_id,
            'domain' => (string) $item->domain,
            'entity_id' => (int) $item->entity_id,
            'master_key' => $item->master_key === null ? null : (string) $item->master_key,
            'master_key_hash' => (string) $item->master_key_hash,
            'manifest_key' => $item->manifest_key === null ? null : (string) $item->manifest_key,
            'preflight_classification' => (string) $item->preflight_classification,
            'phase' => (string) $item->phase,
            'apply_result' => $item->apply_result === null ? null : (string) $item->apply_result,
            'source_sha256' => $item->source_sha256 === null ? null : (string) $item->source_sha256,
            'candidate_manifest_json' => $item->candidate_manifest_json === null
                ? null : (string) $item->candidate_manifest_json,
            'candidate_manifest_sha256' => $item->candidate_manifest_sha256 === null
                ? null : (string) $item->candidate_manifest_sha256,
            'revalidated_at' => $item->revalidated_at === null ? null : (string) $item->revalidated_at,
            'finished_at' => $item->finished_at === null ? null : (string) $item->finished_at,
            'reconciliation_result' => $item->reconciliation_result === null
                ? null : (string) $item->reconciliation_result,
            'reconciliation_event_id' => $item->reconciliation_event_id === null
                ? null : (string) $item->reconciliation_event_id,
            'objects' => array_map(static fn (stdClass $object): array => [
                'id' => (int) $object->id,
                'item_id' => (int) $object->item_id,
                'object_key' => (string) $object->object_key,
                'kind' => (string) $object->kind,
                'expected_sha256' => (string) $object->expected_sha256,
                'expected_size' => (int) $object->expected_size,
                'mime_type' => (string) $object->mime_type,
                'write_state' => (string) $object->write_state,
                'create_state' => $object->create_state === null ? null : (string) $object->create_state,
                'cleanup_state' => (string) $object->cleanup_state,
                'etag' => $object->etag === null ? null : (string) $object->etag,
                'version_id' => $object->version_id === null ? null : (string) $object->version_id,
                'write_intent_at' => $object->write_intent_at === null ? null : (string) $object->write_intent_at,
                'write_confirmed_at' => $object->write_confirmed_at === null
                    ? null : (string) $object->write_confirmed_at,
                'cleanup_attempted_at' => $object->cleanup_attempted_at === null
                    ? null : (string) $object->cleanup_attempted_at,
                'cleanup_finished_at' => $object->cleanup_finished_at === null
                    ? null : (string) $object->cleanup_finished_at,
                'reconciliation_resolution' => $object->reconciliation_resolution === null
                    ? null : (string) $object->reconciliation_resolution,
                'reconciliation_event_id' => $object->reconciliation_event_id === null
                    ? null : (string) $object->reconciliation_event_id,
            ], $objects),
        ];
    }

    private function databaseTimestamp(mixed $canonical): ?string
    {
        if (! is_string($canonical)) {
            return null;
        }
        $moment = DateTimeImmutable::createFromFormat('Y-m-d\TH:i:s\Z', $canonical, new DateTimeZone('UTC'));

        return $moment !== false && $moment->format('Y-m-d\TH:i:s\Z') === $canonical
            ? $moment->format('Y-m-d H:i:s')
            : null;
    }

    private function sha256(string $value): bool
    {
        return preg_match('/\A[0-9a-f]{64}\z/D', $value) === 1;
    }

    private function canonicalUuid(string $value): bool
    {
        return preg_match('/\A[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}\z/D', $value) === 1;
    }
}
