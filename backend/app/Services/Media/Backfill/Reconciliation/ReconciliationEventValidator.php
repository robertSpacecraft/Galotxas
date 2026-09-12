<?php

namespace App\Services\Media\Backfill\Reconciliation;

use App\Services\Media\Backfill\ManagedMediaDomain;
use App\Services\Media\Backfill\Safety\CleanupState;
use App\Services\Media\Backfill\Safety\CreateState;
use App\Services\Media\Backfill\Safety\ObjectWriteState;
use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Database\DatabaseManager;
use stdClass;
use Throwable;

/**
 * Read-only semantic validation of reconciliation events and of the durable pointers that name
 * them. Shared by D2-A inspection and the D2-B2 mutation repository so a syntactically valid UUID
 * is never mistaken for a supported resolution. It never mutates and never touches storage.
 */
final class ReconciliationEventValidator
{
    public const EVIDENCE_VERSION = 1;

    public const MAX_EVIDENCE_BYTES = 16_384;

    private const EVENTS = 'media_backfill_reconciliation_events';

    private const ENVELOPE = ['v', 'kind', 'observed_at', 'storage_identity_hash', 'backend_mode'];

    public function __construct(private readonly DatabaseManager $database) {}

    public function event(string $eventId): ?stdClass
    {
        if (! $this->canonicalUuid($eventId)) {
            return null;
        }

        return $this->database->connection()->table(self::EVENTS)->useWritePdo()
            ->where('event_id', $eventId)->first();
    }

    /** Identifiers, type, scalars and evidence integrity of one row, independent of its parents. */
    public function isStructurallyValid(mixed $event): bool
    {
        if (! $event instanceof stdClass) {
            return false;
        }
        $type = is_string($event->event_type ?? null) ? ReconciliationEventType::tryFrom($event->event_type) : null;
        $itemId = $event->item_id ?? null;
        $revision = $event->code_revision ?? null;
        $json = $event->evidence_json ?? null;
        if ($type === null
            || ! $this->canonicalUuid((string) ($event->event_id ?? ''))
            || ! $this->canonicalUuid((string) ($event->attempt_id ?? ''))
            || ! $this->canonicalUuid((string) ($event->run_id ?? ''))
            || ($itemId !== null && (! is_numeric($itemId) || (int) $itemId < 1))
            || (int) ($event->evidence_version ?? 0) !== self::EVIDENCE_VERSION
            || ! $this->sha256((string) ($event->storage_identity_hash ?? ''))
            || ! $this->sha256((string) ($event->evidence_sha256 ?? ''))
            || ReconciliationBackendMode::tryFrom((string) ($event->backend_mode ?? '')) === null
            || ($revision !== null && (! is_string($revision) || preg_match('/\A[0-9a-f]{40}\z/D', $revision) !== 1))
            || ($event->created_at ?? null) === null
            || ! is_string($json) || $json === '' || strlen($json) > self::MAX_EVIDENCE_BYTES
            || ! hash_equals((string) $event->evidence_sha256, hash('sha256', $json))) {
            return false;
        }
        try {
            $evidence = json_decode($json, true, 8, JSON_THROW_ON_ERROR);
        } catch (Throwable) {
            return false;
        }

        return is_array($evidence) && $this->envelopeIsValid($evidence, $type, $event);
    }

    /**
     * The single structurally valid attempt_started row backing one attempt on one exact run,
     * anchored to the durable storage identity that run was journaled with.
     */
    public function attemptStart(mixed $attemptId, string $runId, mixed $runStorageIdentityHash): ?stdClass
    {
        if (! is_string($attemptId) || ! $this->canonicalUuid($attemptId) || ! $this->canonicalUuid($runId)
            || ! is_string($runStorageIdentityHash) || ! $this->sha256($runStorageIdentityHash)) {
            return null;
        }
        $events = $this->database->connection()->table(self::EVENTS)->useWritePdo()
            ->where('attempt_id', $attemptId);
        $starts = (clone $events)->where('event_type', ReconciliationEventType::AttemptStarted->value)
            ->orderBy('event_id')->limit(2)->get()->all();
        if (count($starts) !== 1 || (clone $events)->where('run_id', '!=', $runId)->exists()) {
            return null;
        }
        $start = $starts[0];
        if (! $this->isStructurallyValid($start) || (string) $start->run_id !== $runId
            || ! hash_equals($runStorageIdentityHash, (string) $start->storage_identity_hash)) {
            return null;
        }

        return $start;
    }

    /**
     * Validated durable link: the pointer must name an existing, structurally valid event of the
     * expected type, with the exact parentage, the durable storage identity of its run and a
     * valid, concordant attempt provenance. The caller states the run identity explicitly, so a
     * pair of events that agree with each other but not with their run stays fail-closed.
     */
    public function projectionEvent(
        mixed $pointer,
        ReconciliationEventType $expected,
        string $runId,
        ?int $itemId,
        mixed $runStorageIdentityHash,
    ): ?stdClass {
        if (! is_string($pointer) || ! is_string($runStorageIdentityHash) || ! $this->sha256($runStorageIdentityHash)) {
            return null;
        }
        $event = $this->event($pointer);
        if (! $this->isStructurallyValid($event) || $event->event_type !== $expected->value
            || (string) $event->run_id !== $runId
            || ($itemId === null ? ($event->item_id ?? null) !== null : (int) ($event->item_id ?? 0) !== $itemId)
            || ! hash_equals($runStorageIdentityHash, (string) $event->storage_identity_hash)) {
            return null;
        }
        $start = $this->attemptStart($event->attempt_id, (string) $event->run_id, $runStorageIdentityHash);
        if ($start === null
            || ! hash_equals((string) $start->storage_identity_hash, (string) $event->storage_identity_hash)
            || $start->backend_mode !== $event->backend_mode
            || ($start->code_revision ?? null) !== ($event->code_revision ?? null)) {
            return null;
        }

        return $event;
    }

    /** @param array<mixed> $evidence */
    private function envelopeIsValid(array $evidence, ReconciliationEventType $type, stdClass $event): bool
    {
        if (array_keys($evidence) !== $this->expectedKeys($type)
            || ($evidence['v'] ?? null) !== self::EVIDENCE_VERSION
            || ($evidence['kind'] ?? null) !== $type->value
            || ! $this->timestampIsCanonical($evidence['observed_at'] ?? null)
            || ! is_string($evidence['storage_identity_hash'] ?? null)
            || ! hash_equals((string) $event->storage_identity_hash, $evidence['storage_identity_hash'])
            || ($evidence['backend_mode'] ?? null) !== $event->backend_mode) {
            return false;
        }
        $hasItem = ($event->item_id ?? null) !== null;
        $itemScoped = in_array($type, [
            ReconciliationEventType::ItemForwardAccepted,
            ReconciliationEventType::ItemNoEffectClosed,
        ], true);
        $runScoped = in_array($type, [
            ReconciliationEventType::AttemptStarted,
            ReconciliationEventType::RunClosedAfterReconciliation,
        ], true);
        if (($itemScoped && ! $hasItem) || ($runScoped && $hasItem)) {
            return false;
        }

        return match ($type) {
            ReconciliationEventType::AttemptBlocked => ReconciliationBlockReason::tryFrom(
                is_string($evidence['reason']) ? $evidence['reason'] : '') !== null,
            ReconciliationEventType::ItemForwardAccepted => $this->forwardBodyIsValid($evidence),
            ReconciliationEventType::ItemNoEffectClosed => $this->noEffectBodyIsValid($evidence),
            default => true,
        };
    }

    /** @return list<string> */
    private function expectedKeys(ReconciliationEventType $type): array
    {
        return match ($type) {
            // The run closure payload belongs to D2-B3; B2 only validates the shared envelope.
            ReconciliationEventType::AttemptStarted,
            ReconciliationEventType::RunClosedAfterReconciliation => self::ENVELOPE,
            ReconciliationEventType::AttemptBlocked => [...self::ENVELOPE, 'reason'],
            ReconciliationEventType::ItemNoEffectClosed => [...self::ENVELOPE, 'objects'],
            ReconciliationEventType::ItemForwardAccepted => [...self::ENVELOPE, 'reference_identity_sha256',
                'master_key_sha256', 'master_sha256', 'candidate_manifest_sha256', 'unique_live_owner_revalidated',
                'live_owner_domain', 'live_owner_entity_id', 'manifest_structure_validated',
                'no_unexpected_canonical_target', 'objects'],
        };
    }

    /** @param array<mixed> $evidence */
    private function forwardBodyIsValid(array $evidence): bool
    {
        foreach (['reference_identity_sha256', 'master_key_sha256', 'master_sha256', 'candidate_manifest_sha256'] as $hash) {
            if (! is_string($evidence[$hash]) || ! $this->sha256($evidence[$hash])) {
                return false;
            }
        }
        foreach (['unique_live_owner_revalidated', 'manifest_structure_validated', 'no_unexpected_canonical_target'] as $flag) {
            if (! is_bool($evidence[$flag])) {
                return false;
            }
        }

        return is_string($evidence['live_owner_domain'])
            && ManagedMediaDomain::tryFrom($evidence['live_owner_domain']) !== null
            && is_int($evidence['live_owner_entity_id']) && $evidence['live_owner_entity_id'] >= 1
            && $this->objectsAreValid($evidence['objects'], false) && $evidence['objects'] !== [];
    }

    /** @param array<mixed> $evidence */
    private function noEffectBodyIsValid(array $evidence): bool
    {
        return $this->objectsAreValid($evidence['objects'], true);
    }

    private function objectsAreValid(mixed $objects, bool $durableFacts): bool
    {
        // range(0, -1) is [0, -1] in PHP: an empty list must be accepted explicitly.
        if (! is_array($objects) || ($objects !== [] && array_keys($objects) !== range(0, count($objects) - 1))) {
            return false;
        }
        $previous = 0;
        foreach ($objects as $object) {
            $expected = $durableFacts
                ? ['object_id', 'write_state', 'create_state', 'cleanup_state', 'attribution']
                : ['object_id', 'observed_sha256', 'observed_size', 'observed_mime_type',
                    'descriptor_validated', 'structure_validated'];
            if (! is_array($object) || array_keys($object) !== $expected
                || ! is_int($object['object_id']) || $object['object_id'] <= $previous) {
                return false;
            }
            $previous = $object['object_id'];
            $valid = $durableFacts
                ? is_string($object['write_state']) && ObjectWriteState::tryFrom($object['write_state']) !== null
                    && ($object['create_state'] === null || (is_string($object['create_state'])
                        && CreateState::tryFrom($object['create_state']) !== null))
                    && is_string($object['cleanup_state']) && CleanupState::tryFrom($object['cleanup_state']) !== null
                    && is_string($object['attribution']) && ObjectAttribution::tryFrom($object['attribution']) !== null
                : is_string($object['observed_sha256']) && $this->sha256($object['observed_sha256'])
                    && is_int($object['observed_size']) && $object['observed_size'] >= 1
                    && is_string($object['observed_mime_type']) && $object['observed_mime_type'] !== ''
                    && is_bool($object['descriptor_validated']) && is_bool($object['structure_validated']);
            if (! $valid) {
                return false;
            }
        }

        return true;
    }

    private function timestampIsCanonical(mixed $value): bool
    {
        if (! is_string($value)) {
            return false;
        }
        $moment = DateTimeImmutable::createFromFormat('Y-m-d\TH:i:s\Z', $value, new DateTimeZone('UTC'));

        return $moment !== false && $moment->format('Y-m-d\TH:i:s\Z') === $value;
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
