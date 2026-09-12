<?php

namespace App\Services\Media\Backfill\Reconciliation;

use App\Services\Media\Backfill\ManagedMediaDomain;
use App\Services\Media\Backfill\PreflightClassification;
use App\Services\Media\Backfill\Safety\ApplyMaintenanceGuard;
use App\Services\Media\Backfill\Safety\ApplyResult;
use App\Services\Media\Backfill\Safety\BackfillSafetyException;
use App\Services\Media\Backfill\Safety\CleanupState;
use App\Services\Media\Backfill\Safety\CreateState;
use App\Services\Media\Backfill\Safety\ItemPhase;
use App\Services\Media\Backfill\Safety\JournalMode;
use App\Services\Media\Backfill\Safety\ObjectKind;
use App\Services\Media\Backfill\Safety\ObjectWriteState;
use App\Services\Media\Backfill\Safety\RunState;
use App\Services\Media\Backfill\Safety\SafetyError;
use App\Services\Media\Backfill\Safety\StorageIdentity;
use Closure;
use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;
use Illuminate\Database\Connection;
use Illuminate\Database\DatabaseManager;
use stdClass;
use Throwable;

/**
 * Private D2 mutation repository: append-only reconciliation events plus terminal item/object
 * projections. It performs no storage observation, write, delete, cleanup, confirmed absence,
 * run closure or checkpoint change, and the APPLY history stays byte-identical. The future
 * coordinator owns the advisory lock and supplies an already observed evidence snapshot.
 */
class ReconciliationJournal
{
    public const EVIDENCE_VERSION = ReconciliationEventValidator::EVIDENCE_VERSION;

    private const EVENTS = 'media_backfill_reconciliation_events';

    private const MAX_OBJECTS = 1000;

    public function __construct(
        private readonly DatabaseManager $database,
        private readonly ApplyMaintenanceGuard $maintenance,
        private readonly ObjectEvidenceClassifier $classifier,
        private readonly CandidateManifestReader $candidates,
        private readonly ReconciliationEventValidator $events,
    ) {}

    /** Immutable provenance that one attempt began for one exact run; changes no projection. */
    public function beginRunReconciliation(
        string $runId,
        string $eventId,
        DateTimeInterface $observedAt,
        ReconciliationContext $context,
    ): ReconciliationEventRecord {
        return $this->commit(
            ReconciliationEventType::AttemptStarted,
            $runId,
            null,
            $eventId,
            $context,
            false,
            fn (): array => [
                $this->envelope(ReconciliationEventType::AttemptStarted, $observedAt, $context->identity->hash, $context->backendMode),
                null,
                null,
            ],
        );
    }

    /** Persists why an attempt refused to resolve. It clears no blocker and resolves nothing. */
    public function recordBlockedAttempt(
        string $runId,
        ?int $itemId,
        string $eventId,
        ReconciliationBlockReason $reason,
        DateTimeInterface $observedAt,
        ReconciliationContext $context,
    ): ReconciliationEventRecord {
        return $this->commit(
            ReconciliationEventType::AttemptBlocked,
            $runId,
            $itemId,
            $eventId,
            $context,
            false,
            fn (): array => [
                $this->envelope(ReconciliationEventType::AttemptBlocked, $observedAt, $context->identity->hash, $context->backendMode)
                    + ['reason' => $reason->value],
                null,
                null,
            ],
        );
    }

    /**
     * Item-atomic forward acceptance of a complete functional publication set already observed and
     * revalidated by the caller. One event resolves the item and every planned object together.
     */
    public function recordForwardItemResolution(
        string $runId,
        int $itemId,
        string $eventId,
        ForwardItemEvidence $evidence,
        ReconciliationContext $context,
    ): ReconciliationEventRecord {
        return $this->commit(
            ReconciliationEventType::ItemForwardAccepted,
            $runId,
            $itemId,
            $eventId,
            $context,
            true,
            function (Connection $db, stdClass $run, ?stdClass $item, array $objects) use ($evidence, $context, $eventId): array {
                $this->assertForwardPlan($item, $objects, $evidence, $context);
                $this->forwardProjectionState($db, $run, $item, $objects, $eventId);

                return [
                    $this->envelope(ReconciliationEventType::ItemForwardAccepted, $evidence->observedAt,
                        $evidence->storageIdentityHash, $evidence->backendMode) + $evidence->canonical(),
                    fn (Connection $connection, string $id): array => $this->applyForward($connection, $item, $objects, $id),
                    fn (Connection $connection, string $id): array => $this->verifyForward($connection, $item, $objects, $id),
                ];
            },
        );
    }

    /**
     * Closes only the item-phase blocker when the immutable APPLY journal itself proves the
     * unfinished item cannot have produced a storage effect. No object projection is written.
     */
    public function recordNoEffectItemResolution(
        string $runId,
        int $itemId,
        string $eventId,
        DateTimeInterface $observedAt,
        ReconciliationContext $context,
    ): ReconciliationEventRecord {
        return $this->commit(
            ReconciliationEventType::ItemNoEffectClosed,
            $runId,
            $itemId,
            $eventId,
            $context,
            true,
            function (Connection $db, stdClass $run, ?stdClass $item, array $objects) use ($observedAt, $context, $eventId): array {
                $this->assertNoEffect($item, $objects);
                $this->noEffectProjectionState($db, $run, $item, $objects, $eventId);

                return [
                    $this->envelope(ReconciliationEventType::ItemNoEffectClosed, $observedAt,
                        $context->identity->hash, $context->backendMode) + $this->noEffectFacts($objects),
                    fn (Connection $connection, string $id): array => $this->applyNoEffect($connection, $item, $id),
                    fn (Connection $connection, string $id): array => $this->verifyNoEffect($connection, $item, $objects, $id),
                ];
            },
        );
    }

    /** @param Closure(Connection, stdClass, ?stdClass, list<stdClass>): array{array<string, mixed>, ?Closure, ?Closure} $prepare */
    private function commit(
        ReconciliationEventType $type,
        string $runId,
        ?int $itemId,
        string $eventId,
        ReconciliationContext $context,
        bool $strictItem,
        Closure $prepare,
    ): ReconciliationEventRecord {
        return $this->safe(function () use ($type, $runId, $itemId, $eventId, $context, $strictItem, $prepare) {
            $this->assertIdentifiers($runId, $itemId, $eventId, $context);
            $this->assertConnection();
            $this->assertOperationalContext($context);

            return $this->transaction(function (Connection $db) use ($type, $runId, $itemId, $eventId, $context, $strictItem, $prepare) {
                $run = $this->lockedRun($db, $runId, $context);
                $item = $itemId === null ? null : $this->lockedItem($db, $run, $itemId, $strictItem);
                $objects = [];
                if ($item !== null && $strictItem) {
                    $objects = $this->lockedObjects($db, $itemId);
                    $this->assertItemReachable($item, $objects);
                }
                $this->assertAttempt($db, $type, $run, $eventId, $context);
                [$evidence, $apply, $verify] = $prepare($db, $run, $item, $objects);
                [$json, $sha] = $this->encode($evidence);
                $existing = $db->table(self::EVENTS)->where('event_id', $eventId)->lockForUpdate()->first();
                if ($existing !== null) {
                    $this->assertReplayable($existing, $type, $runId, $itemId, $context, $json, $sha);
                    $applied = $verify === null ? [null, 0] : $verify($db, $eventId);
                    $this->assertStillOperational($context);

                    return $this->record($type, $eventId, $context, true, $applied);
                }
                $row = [
                    'event_id' => $eventId,
                    'attempt_id' => $context->attemptId,
                    'run_id' => $runId,
                    'item_id' => $itemId,
                    'event_type' => $type->value,
                    'evidence_version' => self::EVIDENCE_VERSION,
                    'storage_identity_hash' => $context->identity->hash,
                    'backend_mode' => $context->backendMode->value,
                    'code_revision' => $context->codeRevision,
                    'evidence_sha256' => $sha,
                    'evidence_json' => $json,
                    'created_at' => now(),
                ];
                // The reader validating durable links must accept everything this writer persists.
                if (! $this->events->isStructurallyValid((object) $row)) {
                    $this->fail(ReconciliationError::InconsistentJournal);
                }
                $db->table(self::EVENTS)->insert($row);
                $applied = $apply === null ? [null, 0] : $apply($db, $eventId);
                // Ownership must still hold immediately before the commit that makes this durable.
                $this->assertStillOperational($context);

                return $this->record($type, $eventId, $context, false, $applied);
            });
        });
    }

    private function assertIdentifiers(string $runId, ?int $itemId, string $eventId, ReconciliationContext $context): void
    {
        if (! $this->canonicalUuid($runId) || ! $this->canonicalUuid($eventId)
            || $eventId === $context->attemptId || ($itemId !== null && $itemId < 1)) {
            $this->fail(ReconciliationError::InvalidInput);
        }
    }

    private function assertConnection(): void
    {
        $db = $this->database->connection();
        if ($db->getDriverName() !== 'mariadb') {
            $this->fail(ReconciliationError::JournalUnavailable);
        }
        if ($db->transactionLevel() !== 0 || $db->getPdo()->inTransaction()) {
            $this->fail(ReconciliationError::AmbientTransaction);
        }
    }

    private function assertOperationalContext(ReconciliationContext $context): void
    {
        $current = StorageIdentity::current($this->database);
        if (! hash_equals($current->hash, $context->identity->hash)
            || ! hash_equals($current->hash, $context->lock->identity->hash)
            || ReconciliationBackendMode::current() !== $context->backendMode) {
            $this->fail(ReconciliationError::IdentityMismatch);
        }
        $this->assertStillOperational($context);
    }

    private function assertStillOperational(ReconciliationContext $context): void
    {
        $this->maintenance->assertAllowed();
        $context->lock->assertOwned();
    }

    private function lockedRun(Connection $db, string $runId, ReconciliationContext $context): stdClass
    {
        $run = $db->table('media_backfill_runs')->where('run_id', $runId)->lockForUpdate()->first();
        if ($run === null) {
            $this->fail(ReconciliationError::InvalidInput);
        }
        if (! is_string($run->storage_identity_hash ?? null)
            || preg_match('/\A[0-9a-f]{64}\z/D', $run->storage_identity_hash) !== 1) {
            $this->fail(ReconciliationError::InconsistentJournal);
        }
        if (! hash_equals($run->storage_identity_hash, $context->identity->hash)) {
            $this->fail(ReconciliationError::IdentityMismatch);
        }
        $state = is_string($run->state ?? null) ? RunState::tryFrom($run->state) : null;
        if (($run->mode ?? null) !== JournalMode::Apply->value || $state === null
            || ($state === RunState::Active) !== (($run->finished_at ?? null) === null)) {
            $this->fail(ReconciliationError::InconsistentJournal);
        }
        $this->runSelection($run);
        // Only D2-B3 may close a run, so any run projection is out of reach for these operations:
        // a malformed pointer is corruption, and a well-formed one is a state B2 must not touch.
        $pointer = $run->reconciliation_event_id ?? null;
        if ($pointer !== null) {
            $this->fail(is_string($pointer) && $this->canonicalUuid($pointer)
                ? ReconciliationError::IllegalResolution
                : ReconciliationError::InconsistentJournal);
        }

        return $run;
    }

    /** @return array{ManagedMediaDomain, int, int, int} */
    private function runSelection(stdClass $run): array
    {
        try {
            $options = json_decode((string) $run->options_json, true, 4, JSON_THROW_ON_ERROR);
            $bounds = json_decode((string) $run->upper_bounds_json, true, 4, JSON_THROW_ON_ERROR);
            $checkpoints = json_decode((string) $run->checkpoints_json, true, 4, JSON_THROW_ON_ERROR);
        } catch (Throwable) {
            $this->fail(ReconciliationError::InconsistentJournal);
        }
        $domain = is_array($options) && is_string($options['domain'] ?? null)
            ? ManagedMediaDomain::tryFrom($options['domain'])
            : null;
        if ($domain === null || array_keys($options) !== ['domain', 'after_id', 'limit']
            || ! is_int($options['after_id']) || $options['after_id'] < 0
            || ! is_int($options['limit']) || $options['limit'] < 1 || $options['limit'] > self::MAX_OBJECTS
            || ! is_array($bounds) || array_keys($bounds) !== [$domain->value] || ! is_int($bounds[$domain->value])
            || $bounds[$domain->value] < 0
            || ! is_array($checkpoints) || array_keys($checkpoints) !== [$domain->value]
            || ! is_int($checkpoints[$domain->value]) || $checkpoints[$domain->value] < $options['after_id']
            || ($checkpoints[$domain->value] > $bounds[$domain->value]
                && $checkpoints[$domain->value] !== $options['after_id'])) {
            $this->fail(ReconciliationError::InconsistentJournal);
        }

        return [$domain, $options['after_id'], $options['limit'], $bounds[$domain->value]];
    }

    private function lockedItem(Connection $db, stdClass $run, int $itemId, bool $strict): stdClass
    {
        $item = $db->table('media_backfill_items')->where('id', $itemId)->lockForUpdate()->first();
        if ($item === null) {
            $this->fail(ReconciliationError::InvalidInput);
        }
        if ((string) ($item->run_id ?? '') !== (string) $run->run_id) {
            $this->fail(ReconciliationError::InconsistentJournal);
        }
        if (! $strict) {
            return $item;
        }
        [$domain, $afterId, , $upperBound] = $this->runSelection($run);
        $phase = is_string($item->phase ?? null) ? ItemPhase::tryFrom($item->phase) : null;
        $result = is_string($item->apply_result ?? null) ? ApplyResult::tryFrom($item->apply_result) : null;
        $preflight = is_string($item->preflight_classification ?? null)
            ? PreflightClassification::tryFrom($item->preflight_classification)
            : null;
        $entityId = (int) ($item->entity_id ?? 0);
        if ($phase === null || $preflight === null || ($item->domain ?? null) !== $domain->value
            || $entityId <= $afterId || $entityId > $upperBound
            || ($item->apply_result ?? null) !== $result?->value
            || ($phase === ItemPhase::Finished) !== ($result !== null)
            || ($result !== null) !== (($item->finished_at ?? null) !== null)) {
            $this->fail(ReconciliationError::InconsistentJournal);
        }
        $this->assertCandidateMetadata($item, $preflight, $phase);

        return $item;
    }

    /** The candidate metadata must be exactly the shape a valid snapshot produces for this classification. */
    private function assertCandidateMetadata(stdClass $item, PreflightClassification $preflight, ItemPhase $phase): void
    {
        $master = $item->master_key ?? null;
        if (! is_string($item->master_key_hash ?? null)
            || preg_match('/\A[0-9a-f]{64}\z/D', $item->master_key_hash) !== 1
            || ($master === null) !== (($item->manifest_key ?? null) === null)) {
            $this->fail(ReconciliationError::InconsistentJournal);
        }
        if ($preflight === PreflightClassification::LegacyBackfillable) {
            [$manifest, $coherent] = $this->candidates->read($item);
            if (! $coherent || $manifest === null || ! is_string($item->source_sha256 ?? null)
                || preg_match('/\A[0-9a-f]{64}\z/D', $item->source_sha256) !== 1) {
                $this->fail(ReconciliationError::InconsistentJournal);
            }

            return;
        }
        // Only a backfillable candidate carries candidate metadata, and only it can be revalidated.
        if (($item->candidate_manifest_json ?? null) !== null
            || ($item->candidate_manifest_sha256 ?? null) !== null
            || ($item->source_sha256 ?? null) !== null
            || ! in_array($phase, [ItemPhase::Inspected, ItemPhase::Finished], true)
            || (is_string($master) && ! hash_equals(hash('sha256', $master), $item->master_key_hash))) {
            $this->fail(ReconciliationError::InconsistentJournal);
        }
    }

    /**
     * Cross-row reachability under the accepted APPLY state machine: only a coherent candidate can
     * plan objects, and `writing` is only reachable once an object has been dispatched.
     *
     * @param  list<stdClass>  $objects
     */
    private function assertItemReachable(stdClass $item, array $objects): void
    {
        $preflight = is_string($item->preflight_classification ?? null)
            ? PreflightClassification::tryFrom($item->preflight_classification)
            : null;
        if ($preflight !== PreflightClassification::LegacyBackfillable && $objects !== []) {
            $this->fail(ReconciliationError::InconsistentJournal);
        }
        if ((is_string($item->phase ?? null) ? ItemPhase::tryFrom($item->phase) : null) !== ItemPhase::Writing) {
            return;
        }
        foreach ($objects as $object) {
            if (($object->write_state ?? null) !== ObjectWriteState::Planned->value) {
                return;
            }
        }
        $this->fail(ReconciliationError::InconsistentJournal);
    }

    /** @return list<stdClass> Rows locked strictly in ascending journal order. */
    private function lockedObjects(Connection $db, int $itemId): array
    {
        $ids = $db->table('media_backfill_objects')->where('item_id', $itemId)
            ->orderBy('id')->limit(self::MAX_OBJECTS + 1)->pluck('id')->all();
        if (count($ids) > self::MAX_OBJECTS) {
            $this->fail(ReconciliationError::InconsistentJournal);
        }
        $objects = [];
        foreach ($ids as $id) {
            $object = $db->table('media_backfill_objects')->where('id', (int) $id)->lockForUpdate()->first();
            if ($object === null || (int) ($object->item_id ?? 0) !== $itemId) {
                $this->fail(ReconciliationError::InconsistentJournal);
            }
            $objects[] = $object;
        }
        if ($db->table('media_backfill_objects')->where('item_id', $itemId)->count() !== count($objects)) {
            $this->fail(ReconciliationError::InconsistentJournal);
        }

        return $objects;
    }

    private function assertAttempt(
        Connection $db,
        ReconciliationEventType $type,
        stdClass $run,
        string $eventId,
        ReconciliationContext $context,
    ): void {
        $runId = (string) $run->run_id;
        if ($db->table(self::EVENTS)->where('attempt_id', $context->attemptId)->where('run_id', '!=', $runId)->exists()) {
            $this->fail(ReconciliationError::ReplayConflict);
        }
        $starts = $db->table(self::EVENTS)->where('attempt_id', $context->attemptId)
            ->where('event_type', ReconciliationEventType::AttemptStarted->value)
            ->orderBy('event_id')->limit(2)->pluck('event_id')->all();
        if ($type === ReconciliationEventType::AttemptStarted) {
            if ($starts !== [] && $starts !== [$eventId]) {
                $this->fail(ReconciliationError::ReplayConflict);
            }

            return;
        }
        // A resolution may only continue an attempt whose start event is itself semantically valid.
        $start = $this->events->attemptStart($context->attemptId, $runId, $run->storage_identity_hash);
        if ($start === null) {
            $this->fail($starts === []
                ? ReconciliationError::IllegalResolution
                : ReconciliationError::InconsistentJournal);
        }
        if (! hash_equals((string) $start->storage_identity_hash, $context->identity->hash)
            || $start->backend_mode !== $context->backendMode->value
            || ($start->code_revision ?? null) !== $context->codeRevision) {
            $this->fail(ReconciliationError::InconsistentJournal);
        }
    }

    private function assertReplayable(
        stdClass $existing,
        ReconciliationEventType $type,
        string $runId,
        ?int $itemId,
        ReconciliationContext $context,
        string $json,
        string $sha,
    ): void {
        if (! $this->events->isStructurallyValid($existing)) {
            $this->fail(ReconciliationError::InconsistentJournal);
        }
        $identical = (string) ($existing->attempt_id ?? '') === $context->attemptId
            && (string) ($existing->run_id ?? '') === $runId
            && (($existing->item_id ?? null) === null ? null : (int) $existing->item_id) === $itemId
            && ($existing->event_type ?? null) === $type->value
            && (int) ($existing->evidence_version ?? 0) === self::EVIDENCE_VERSION
            && is_string($existing->storage_identity_hash ?? null)
            && hash_equals($existing->storage_identity_hash, $context->identity->hash)
            && ($existing->backend_mode ?? null) === $context->backendMode->value
            && ($existing->code_revision ?? null) === $context->codeRevision
            && $existing->evidence_json === $json
            && hash_equals($existing->evidence_sha256, $sha);
        if (! $identical) {
            $this->fail(ReconciliationError::ReplayConflict);
        }
    }

    private function assertForwardPlan(
        stdClass $item,
        array $objects,
        ForwardItemEvidence $evidence,
        ReconciliationContext $context,
    ): void {
        if (! hash_equals($evidence->storageIdentityHash, $context->identity->hash)
            || $evidence->backendMode !== $context->backendMode) {
            $this->fail(ReconciliationError::EvidenceMismatch);
        }
        [$manifest, $coherent] = $this->candidates->read($item);
        if (! $coherent || $manifest === null) {
            $this->fail(ReconciliationError::InconsistentJournal);
        }
        $master = (string) $item->master_key;
        $reference = substr($master, 0, (int) strrpos($master, '.'));
        if (! $evidence->uniqueLiveOwnerRevalidated || ! $evidence->manifestStructureValidated
            || ! $evidence->noUnexpectedCanonicalTarget
            || $evidence->liveOwnerDomain !== $item->domain
            || $evidence->liveOwnerEntityId !== (int) $item->entity_id
            || ! hash_equals($evidence->referenceIdentitySha256, hash('sha256', $reference))
            || ! hash_equals($evidence->masterKeySha256, (string) $item->master_key_hash)
            || ! hash_equals($evidence->masterSha256, (string) $item->source_sha256)
            || ! hash_equals($evidence->candidateManifestSha256, (string) $item->candidate_manifest_sha256)) {
            $this->fail(ReconciliationError::EvidenceMismatch);
        }
        $expected = [(string) $item->manifest_key => null];
        foreach ($manifest->variants as $descriptor) {
            $expected[$descriptor->key] = $descriptor;
        }
        if (count($expected) !== count($manifest->variants) + 1 || count($objects) !== count($expected)) {
            $this->fail(ReconciliationError::InconsistentJournal);
        }
        foreach ($objects as $object) {
            $key = is_string($object->object_key ?? null) ? $object->object_key : '';
            if (! array_key_exists($key, $expected)
                || $this->classifier->attributionFor($object) === ObjectAttribution::InconsistentJournal) {
                $this->fail(ReconciliationError::InconsistentJournal);
            }
            $descriptor = $expected[$key];
            unset($expected[$key]);
            if (CleanupState::tryFrom((string) $object->cleanup_state) === CleanupState::Deleted) {
                $this->fail(ReconciliationError::IllegalResolution);
            }
            $planned = $descriptor === null
                ? ($object->kind ?? null) === ObjectKind::Manifest->value
                    && hash_equals((string) $object->expected_sha256, (string) $item->candidate_manifest_sha256)
                    && (int) $object->expected_size === strlen((string) $item->candidate_manifest_json)
                    && ($object->mime_type ?? null) === 'application/json'
                : ($object->kind ?? null) === ObjectKind::Variant->value
                    && (int) $object->expected_size === $descriptor->size
                    && ($object->mime_type ?? null) === $descriptor->mimeType;
            if (! $planned) {
                $this->fail(ReconciliationError::InconsistentJournal);
            }
        }
        if ($expected !== []) {
            $this->fail(ReconciliationError::InconsistentJournal);
        }
        $this->assertObservedObjects($objects, $evidence);
    }

    private function assertObservedObjects(array $objects, ForwardItemEvidence $evidence): void
    {
        $rows = [];
        foreach ($objects as $object) {
            $rows[(int) $object->id] = $object;
        }
        if ($evidence->objectIds() !== array_keys($rows)) {
            $this->fail(ReconciliationError::EvidenceMismatch);
        }
        foreach ($evidence->objects as $observed) {
            $row = $rows[$observed->objectId];
            if (! $observed->descriptorValidated || ! $observed->structureValidated
                || ! hash_equals($observed->observedSha256, (string) $row->expected_sha256)
                || $observed->observedSize !== (int) $row->expected_size
                || $observed->observedMimeType !== ($row->mime_type ?? null)) {
                $this->fail(ReconciliationError::EvidenceMismatch);
            }
        }
    }

    private function assertNoEffect(stdClass $item, array $objects): void
    {
        $phase = is_string($item->phase ?? null) ? ItemPhase::tryFrom($item->phase) : null;
        if ($phase === null || $phase === ItemPhase::Finished || ($item->apply_result ?? null) !== null
            || ($item->finished_at ?? null) !== null) {
            $this->fail(ReconciliationError::IllegalResolution);
        }
        foreach ($objects as $object) {
            $attribution = $this->classifier->attributionFor($object);
            if ($attribution === ObjectAttribution::InconsistentJournal) {
                $this->fail(ReconciliationError::InconsistentJournal);
            }
            $write = is_string($object->write_state ?? null) ? ObjectWriteState::tryFrom($object->write_state) : null;
            $create = is_string($object->create_state ?? null) ? CreateState::tryFrom($object->create_state) : null;
            // Only durable history that cannot have created or owned an object qualifies.
            if (! in_array($attribution, [ObjectAttribution::NotDispatched, ObjectAttribution::RejectedCollision,
                ObjectAttribution::FailedWithoutWrite], true)
                || ! in_array($write, [ObjectWriteState::Planned, ObjectWriteState::Rejected], true)
                || ! in_array($create, [null, CreateState::Rejected, CreateState::Failed], true)
                || CleanupState::tryFrom((string) $object->cleanup_state) !== CleanupState::NotRequired
                || ($object->etag ?? null) !== null || ($object->version_id ?? null) !== null
                || ($object->write_confirmed_at ?? null) !== null
                || ($object->cleanup_attempted_at ?? null) !== null
                || ($object->cleanup_finished_at ?? null) !== null) {
                $this->fail(ReconciliationError::IllegalResolution);
            }
        }
    }

    /** @return array<string, mixed> */
    private function noEffectFacts(array $objects): array
    {
        return ['objects' => array_map(fn (stdClass $object): array => [
            'object_id' => (int) $object->id,
            'write_state' => (string) $object->write_state,
            'create_state' => $object->create_state === null ? null : (string) $object->create_state,
            'cleanup_state' => (string) $object->cleanup_state,
            'attribution' => $this->classifier->attributionFor($object)->value,
        ], $objects)];
    }

    /** @return bool True when the durable projections already are this exact event. */
    private function forwardProjectionState(
        Connection $db,
        stdClass $run,
        stdClass $item,
        array $objects,
        string $eventId,
    ): bool {
        [$result, $pointer, $states] = $this->projections($db, $run, $item, $objects);
        if ($result === null && $pointer === null && $this->allProjectionsAbsent($states)) {
            return false;
        }
        if ($result !== null && $result !== ItemReconciliationResult::ForwardAccepted) {
            $this->fail(ReconciliationError::ReplayConflict);
        }
        $terminal = $result === ItemReconciliationResult::ForwardAccepted && $objects !== [];
        foreach ($states as $state) {
            $terminal = $terminal && $state[0] === ObjectReconciliationResolution::ForwardRetained && $state[1] === $pointer;
        }
        if (! $terminal) {
            $this->fail(ReconciliationError::InconsistentJournal);
        }
        if ($pointer !== $eventId) {
            $this->fail(ReconciliationError::ReplayConflict);
        }

        return true;
    }

    /** @return bool True when the durable projections already are this exact event. */
    private function noEffectProjectionState(
        Connection $db,
        stdClass $run,
        stdClass $item,
        array $objects,
        string $eventId,
    ): bool {
        [$result, $pointer, $states] = $this->projections($db, $run, $item, $objects);
        if (! $this->allProjectionsAbsent($states)) {
            $this->fail(ReconciliationError::InconsistentJournal);
        }
        if ($result === null && $pointer === null) {
            return false;
        }
        if ($result !== ItemReconciliationResult::ClosedNoEffect) {
            $this->fail(ReconciliationError::ReplayConflict);
        }
        if ($pointer !== $eventId) {
            $this->fail(ReconciliationError::ReplayConflict);
        }

        return true;
    }

    /** @param array<int, array{?ObjectReconciliationResolution, ?string}> $states */
    private function allProjectionsAbsent(array $states): bool
    {
        foreach ($states as $state) {
            if ($state[0] !== null || $state[1] !== null) {
                return false;
            }
        }

        return true;
    }

    /** @return array{?ItemReconciliationResult, ?string, array<int, array{?ObjectReconciliationResolution, ?string}>} */
    private function projections(Connection $db, stdClass $run, stdClass $item, array $objects): array
    {
        $itemId = (int) $item->id;
        $runId = (string) $item->run_id;
        $identity = $run->storage_identity_hash;
        [$result, $pointer] = $this->projection($item->reconciliation_result ?? null,
            $item->reconciliation_event_id ?? null, ItemReconciliationResult::class);
        if ($pointer !== null) {
            $this->assertLink($pointer, $runId, $itemId, $identity,
                $result === ItemReconciliationResult::ForwardAccepted
                    ? ReconciliationEventType::ItemForwardAccepted
                    : ReconciliationEventType::ItemNoEffectClosed);
        }
        $states = [];
        foreach ($objects as $object) {
            $state = $this->projection($object->reconciliation_resolution ?? null,
                $object->reconciliation_event_id ?? null, ObjectReconciliationResolution::class);
            if ($state[1] !== null) {
                $this->assertLink($state[1], $runId, $itemId, $identity,
                    ReconciliationEventType::ItemForwardAccepted);
            }
            $states[(int) $object->id] = $state;
        }

        return [$result, $pointer, $states];
    }

    /**
     * @param  class-string<ItemReconciliationResult|ObjectReconciliationResolution>  $enum
     * @return array{null|ItemReconciliationResult|ObjectReconciliationResolution, ?string}
     */
    private function projection(mixed $value, mixed $pointer, string $enum): array
    {
        if ($value === null && $pointer === null) {
            return [null, null];
        }
        if (! is_string($value) || ! is_string($pointer) || ! $this->canonicalUuid($pointer)) {
            $this->fail(ReconciliationError::InconsistentJournal);
        }
        $resolution = $enum::tryFrom($value);
        if ($resolution === null) {
            $this->fail(ReconciliationError::InconsistentJournal);
        }

        return [$resolution, $pointer];
    }

    /** A durable pointer must resolve to a valid event of the expected type, parent and provenance. */
    private function assertLink(
        string $eventId,
        string $runId,
        int $itemId,
        mixed $runStorageIdentityHash,
        ReconciliationEventType $expected,
    ): void {
        if ($this->events->projectionEvent($eventId, $expected, $runId, $itemId, $runStorageIdentityHash) === null) {
            $this->fail(ReconciliationError::InconsistentJournal);
        }
    }

    /** @return array{?ItemReconciliationResult, int} */
    private function applyForward(Connection $db, stdClass $item, array $objects, string $eventId): array
    {
        foreach ($objects as $object) {
            $this->project($db, 'media_backfill_objects', (int) $object->id, [
                'reconciliation_resolution' => ObjectReconciliationResolution::ForwardRetained->value,
                'reconciliation_event_id' => $eventId,
            ], 'reconciliation_resolution');
        }
        $this->project($db, 'media_backfill_items', (int) $item->id, [
            'reconciliation_result' => ItemReconciliationResult::ForwardAccepted->value,
            'reconciliation_event_id' => $eventId,
        ], 'reconciliation_result');

        return [ItemReconciliationResult::ForwardAccepted, count($objects)];
    }

    /** @return array{?ItemReconciliationResult, int} */
    private function applyNoEffect(Connection $db, stdClass $item, string $eventId): array
    {
        $this->project($db, 'media_backfill_items', (int) $item->id, [
            'reconciliation_result' => ItemReconciliationResult::ClosedNoEffect->value,
            'reconciliation_event_id' => $eventId,
        ], 'reconciliation_result');

        return [ItemReconciliationResult::ClosedNoEffect, 0];
    }

    /** Writes the two projection columns only; every APPLY column stays untouched. */
    private function project(Connection $db, string $table, int $id, array $values, string $column): void
    {
        $updated = $db->table($table)->where('id', $id)
            ->whereNull($column)->whereNull('reconciliation_event_id')->update($values);
        if ($updated !== 1) {
            $this->fail(ReconciliationError::InconsistentJournal);
        }
    }

    /** @return array{?ItemReconciliationResult, int} */
    private function verifyForward(Connection $db, stdClass $item, array $objects, string $eventId): array
    {
        $fresh = $db->table('media_backfill_items')->where('id', (int) $item->id)->first();
        $ids = array_map(static fn (stdClass $object): int => (int) $object->id, $objects);
        $rows = $ids === [] ? [] : $db->table('media_backfill_objects')->whereIn('id', $ids)->orderBy('id')->get()->all();
        $valid = $fresh !== null && count($rows) === count($ids)
            && ($fresh->reconciliation_result ?? null) === ItemReconciliationResult::ForwardAccepted->value
            && ($fresh->reconciliation_event_id ?? null) === $eventId;
        foreach ($rows as $row) {
            $valid = $valid
                && ($row->reconciliation_resolution ?? null) === ObjectReconciliationResolution::ForwardRetained->value
                && ($row->reconciliation_event_id ?? null) === $eventId;
        }
        if (! $valid) {
            $this->fail(ReconciliationError::InconsistentJournal);
        }

        return [ItemReconciliationResult::ForwardAccepted, count($rows)];
    }

    /** @return array{?ItemReconciliationResult, int} */
    private function verifyNoEffect(Connection $db, stdClass $item, array $objects, string $eventId): array
    {
        $fresh = $db->table('media_backfill_items')->where('id', (int) $item->id)->first();
        $projected = $objects === [] ? 0 : $db->table('media_backfill_objects')
            ->whereIn('id', array_map(static fn (stdClass $object): int => (int) $object->id, $objects))
            ->where(fn ($query) => $query->whereNotNull('reconciliation_resolution')
                ->orWhereNotNull('reconciliation_event_id'))->count();
        if ($fresh === null || $projected !== 0
            || ($fresh->reconciliation_result ?? null) !== ItemReconciliationResult::ClosedNoEffect->value
            || ($fresh->reconciliation_event_id ?? null) !== $eventId) {
            $this->fail(ReconciliationError::InconsistentJournal);
        }

        return [ItemReconciliationResult::ClosedNoEffect, 0];
    }

    /** @return array<string, mixed> */
    private function envelope(
        ReconciliationEventType $type,
        DateTimeInterface $observedAt,
        string $identityHash,
        ReconciliationBackendMode $mode,
    ): array {
        return [
            'v' => self::EVIDENCE_VERSION,
            'kind' => $type->value,
            'observed_at' => $this->timestamp($observedAt),
            'storage_identity_hash' => $identityHash,
            'backend_mode' => $mode->value,
        ];
    }

    private function timestamp(DateTimeInterface $observedAt): string
    {
        $moment = DateTimeImmutable::createFromInterface($observedAt)->setTimezone(new DateTimeZone('UTC'));
        if ($moment->getTimestamp() < 1) {
            $this->fail(ReconciliationError::InvalidInput);
        }

        return $moment->format('Y-m-d\TH:i:s\Z');
    }

    /** Canonical fixed-order encoding: the hash covers exactly the stored bytes. @return array{string, string} */
    private function encode(array $evidence): array
    {
        try {
            $json = json_encode($evidence, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        } catch (Throwable) {
            $this->fail(ReconciliationError::InvalidInput);
        }
        if (strlen($json) > ReconciliationEventValidator::MAX_EVIDENCE_BYTES) {
            $this->fail(ReconciliationError::InvalidInput);
        }

        return [$json, hash('sha256', $json)];
    }

    /** @param array{?ItemReconciliationResult, int} $applied */
    private function record(
        ReconciliationEventType $type,
        string $eventId,
        ReconciliationContext $context,
        bool $replayed,
        array $applied,
    ): ReconciliationEventRecord {
        return new ReconciliationEventRecord(
            type: $type,
            eventFingerprint: substr(hash('sha256', $eventId), 0, 16),
            attemptFingerprint: substr(hash('sha256', $context->attemptId), 0, 16),
            evidenceVersion: self::EVIDENCE_VERSION,
            replayed: $replayed,
            itemResult: $applied[0],
            objectsResolved: $applied[1],
        );
    }

    private function transaction(Closure $callback): mixed
    {
        $db = $this->database->connection();
        if ($db->getDriverName() !== 'mariadb') {
            $this->fail(ReconciliationError::JournalUnavailable);
        }
        if ($db->transactionLevel() !== 0 || $db->getPdo()->inTransaction()) {
            $this->fail(ReconciliationError::AmbientTransaction);
        }

        // Explicit lifecycle, as in ApplyJournal: keep the level until rollback succeeds so no
        // uncommitted projection can ever become visible to a later call on this session.
        try {
            $db->beginTransaction();
            $result = $callback($db);
            $db->commit();

            return $result;
        } catch (Throwable $error) {
            try {
                $db->rollBack(0);
            } catch (Throwable) {
                $db->disconnect();
            }

            throw $error;
        }
    }

    private function safe(Closure $callback): mixed
    {
        try {
            return $callback();
        } catch (ReconciliationException $error) {
            throw $error;
        } catch (BackfillSafetyException $error) {
            throw new ReconciliationException(match ($error->reason) {
                SafetyError::MaintenanceRequired => ReconciliationError::MaintenanceRequired,
                SafetyError::LockLost, SafetyError::LockReleaseFailed => ReconciliationError::LockLost,
                SafetyError::IdentityMismatch => ReconciliationError::IdentityMismatch,
                SafetyError::UnsupportedStorage => ReconciliationError::UnsupportedStorage,
                SafetyError::InvalidInput => ReconciliationError::InvalidInput,
                SafetyError::AmbientTransaction => ReconciliationError::AmbientTransaction,
                default => ReconciliationError::JournalUnavailable,
            });
        } catch (Throwable) {
            throw new ReconciliationException(ReconciliationError::JournalUnavailable);
        }
    }

    private function fail(ReconciliationError $reason): never
    {
        throw new ReconciliationException($reason);
    }

    private function canonicalUuid(string $value): bool
    {
        return preg_match('/\A[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}\z/D', $value) === 1;
    }
}
