<?php

namespace App\Services\Media\Backfill\Safety;

use App\Services\Media\Backfill\InspectionReason;
use App\Services\Media\Backfill\ManagedMediaDomain;
use App\Services\Media\Backfill\PreflightClassification;
use App\Services\Media\Backfill\PreflightResult;
use App\Services\Media\Backfill\Reconciliation\ReconciliationStateValidator;
use App\Services\Media\MediaObjectKeyGenerator;
use App\Services\Media\ResponsiveManifest;
use App\Services\Media\ResponsiveMediaKeys;
use App\Services\Media\VariantPolicyVersion;
use Closure;
use Illuminate\Database\Connection;
use Illuminate\Database\DatabaseManager;
use Illuminate\Support\Str;
use stdClass;
use Throwable;

/** Private operational repository. No domain writes, storage I/O or automatic retries. */
class ApplyJournal
{
    public function __construct(
        private readonly DatabaseManager $database,
        private readonly ?ReconciliationStateValidator $reconciliation = null,
    ) {}

    public function createApplyRun(StorageIdentity $identity, ApplyRunSelection $selection, ?string $codeRevision = null): string
    {
        if ($codeRevision !== null && preg_match('/\A[0-9a-f]{40}\z/', $codeRevision) !== 1) {
            throw new BackfillSafetyException(SafetyError::InvalidInput);
        }
        $options = json_encode($selection->options(), JSON_THROW_ON_ERROR);
        $upperBounds = json_encode($selection->upperBounds(), JSON_THROW_ON_ERROR);
        $checkpoints = json_encode($selection->checkpoints(), JSON_THROW_ON_ERROR);

        return $this->transaction(function (Connection $db) use ($identity, $codeRevision, $options, $upperBounds, $checkpoints) {
            $id = (string) Str::uuid();
            $db->table('media_backfill_runs')->insert([
                'run_id' => $id, 'mode' => JournalMode::Apply->value, 'state' => RunState::Active->value,
                'storage_identity_hash' => $identity->hash, 'code_revision' => $codeRevision,
                'options_json' => $options, 'upper_bounds_json' => $upperBounds, 'checkpoints_json' => $checkpoints,
                'started_at' => now(), 'created_at' => now(), 'updated_at' => now(),
            ]);

            return $id;
        });
    }

    /** Replaces a snapshot only while inspected, before any targets have been planned. */
    public function snapshot(string $runId, PreflightResult $result, ?string $sourceSha256 = null, ?string $candidateJson = null): int
    {
        $reference = $result->reference;
        if ($reference->id < 1) {
            throw new BackfillSafetyException(SafetyError::InvalidInput);
        }
        $values = JournalPayload::reference($reference->masterKey);
        $keys = new ResponsiveMediaKeys(new MediaObjectKeyGenerator);
        $sourceSha256 ??= $result->prepared?->masterSha256;
        $candidateJson ??= $result->prepared?->manifest->toJson();
        $manifestKey = $values['master_key'] ? $keys->manifest($values['master_key'], VariantPolicyVersion::V1) : null;
        if ($sourceSha256 !== null) {
            JournalPayload::sha256($sourceSha256);
        }
        if ($candidateJson !== null) {
            JournalPayload::manifest($candidateJson);
            try {
                $manifest = ResponsiveManifest::fromJson($candidateJson, $values['master_key'] ?? '', $manifestKey ?? '', $keys);
                if ($manifest->profile !== $reference->domain->profile()) {
                    throw new BackfillSafetyException(SafetyError::InvalidInput);
                }
            } catch (Throwable) {
                throw new BackfillSafetyException(SafetyError::InvalidInput);
            }
        }
        $values += [
            'manifest_key' => $manifestKey, 'preflight_classification' => $result->classification->value,
            'reason_codes_json' => json_encode(array_values(array_unique(array_map(fn (InspectionReason $r) => $r->value, $result->reasons))), JSON_THROW_ON_ERROR),
            'source_sha256' => $sourceSha256, 'candidate_manifest_json' => $candidateJson,
            'candidate_manifest_sha256' => $candidateJson !== null ? hash('sha256', $candidateJson) : null,
            'inspected_at' => now(), 'updated_at' => now(),
        ];

        return $this->transaction(function (Connection $db) use ($runId, $reference, $values) {
            $run = $this->lockRun($db, $runId);
            $selection = $this->runSelection($run);
            $this->require($reference->domain === $selection->domain
                && $reference->id > $selection->afterId && $reference->id <= $selection->upperBound);
            $query = $db->table('media_backfill_items')->where('run_id', $runId)->where('domain', $reference->domain->value)->where('entity_id', $reference->id);
            $item = $query->lockForUpdate()->first();
            if ($item) {
                $this->require($item->phase === ItemPhase::Inspected->value
                    && ! $db->table('media_backfill_objects')->where('item_id', $item->id)->exists());
                $query->update($values);

                return (int) $item->id;
            }
            $checkpoint = $this->checkpoint($run, $selection);
            $this->require($reference->id > $checkpoint
                && $db->table('media_backfill_items')->where('run_id', $runId)->count() < $selection->limit);

            return $db->table('media_backfill_items')->insertGetId($values + [
                'run_id' => $runId, 'domain' => $reference->domain->value, 'entity_id' => $reference->id,
                'phase' => ItemPhase::Inspected->value, 'created_at' => now(),
            ]);
        });
    }

    public function advanceCheckpoint(string $runId, ManagedMediaDomain $domain, int $entityId): void
    {
        if ($entityId < 0) {
            throw new BackfillSafetyException(SafetyError::InvalidInput);
        }

        $this->transaction(function (Connection $db) use ($runId, $domain, $entityId) {
            $run = $this->lockRun($db, $runId);
            $selection = $this->runSelection($run);
            if ($domain !== $selection->domain) {
                throw new BackfillSafetyException(SafetyError::InvalidInput);
            }
            $checkpoint = $this->checkpoint($run, $selection);
            if ($entityId === $checkpoint) {
                return;
            }
            $this->require($entityId > $checkpoint && $entityId <= $selection->upperBound);
            $item = $db->table('media_backfill_items')->where('run_id', $runId)
                ->where('domain', $domain->value)->where('entity_id', $entityId)->lockForUpdate()->first();
            $this->require($item !== null && $item->phase === ItemPhase::Finished->value);
            $this->require(! $db->table('media_backfill_items')->where('run_id', $runId)
                ->where('domain', $domain->value)->where('entity_id', '>', $checkpoint)->where('entity_id', '<=', $entityId)
                ->where('phase', '!=', ItemPhase::Finished->value)->exists());
            $db->table('media_backfill_runs')->where('run_id', $runId)->update([
                'checkpoints_json' => json_encode([$domain->value => $entityId], JSON_THROW_ON_ERROR),
                'updated_at' => now(),
            ]);
        });
    }

    /** Caller attests that fresh domain/storage revalidation has succeeded; D1C supplies that check. */
    public function markRevalidated(int $itemId): void
    {
        $this->transaction(function (Connection $db) use ($itemId) {
            $item = $this->lockItem($db, $itemId);
            $this->require($item->phase === ItemPhase::Inspected->value
                && $item->preflight_classification === PreflightClassification::LegacyBackfillable->value
                && $item->master_key !== null && $item->source_sha256 !== null && $item->candidate_manifest_json !== null);
            $db->table('media_backfill_items')->where('id', $itemId)->update([
                'phase' => ItemPhase::Revalidated->value, 'revalidated_at' => now(), 'updated_at' => now(),
            ]);
        });
    }

    public function planObject(int $itemId, TargetObject $target): int
    {
        return $this->transaction(function (Connection $db) use ($itemId, $target) {
            $item = $this->lockItem($db, $itemId);
            $this->require(in_array($item->phase, [ItemPhase::Inspected->value, ItemPhase::Revalidated->value], true)
                && $item->master_key !== null && $item->candidate_manifest_json !== null
                && $item->manifest_key === 'variants/v1/'.$target->identity().'/manifest.json');
            if ($target->kind === ObjectKind::Manifest) {
                $this->require($item->candidate_manifest_sha256 === $target->sha256
                    && strlen($item->candidate_manifest_json ?? '') === $target->size);
            } else {
                $candidate = json_decode($item->candidate_manifest_json, true, 16, JSON_THROW_ON_ERROR);
                $this->require(collect($candidate['variants'])->contains(fn ($variant) => $variant['key'] === $target->key
                    && $variant['size'] === $target->size && $variant['mime_type'] === $target->mimeType));
            }
            $this->require(! $db->table('media_backfill_objects')->where('item_id', $itemId)->where('object_key', $target->key)->exists());

            return $db->table('media_backfill_objects')->insertGetId([
                'item_id' => $itemId, 'object_key' => $target->key, 'kind' => $target->kind->value,
                'expected_sha256' => $target->sha256, 'expected_size' => $target->size, 'mime_type' => $target->mimeType,
                'write_state' => ObjectWriteState::Planned->value, 'cleanup_state' => CleanupState::NotRequired->value,
                'created_at' => now(), 'updated_at' => now(),
            ]);
        });
    }

    public function commitIntent(int $objectId): void
    {
        $this->transaction(function (Connection $db) use ($objectId) {
            [$object, $item] = $this->lockObject($db, $objectId);
            $this->require($object->write_state === ObjectWriteState::Planned->value
                && in_array($item->phase, [ItemPhase::Revalidated->value, ItemPhase::Writing->value], true));
            $db->table('media_backfill_objects')->where('id', $objectId)->update([
                'write_state' => ObjectWriteState::Intent->value, 'write_intent_at' => now(), 'updated_at' => now(),
            ]);
            $db->table('media_backfill_items')->where('id', $item->id)->update(['phase' => ItemPhase::Writing->value, 'updated_at' => now()]);
        });
    }

    public function recordReceipt(int $objectId, CreateReceipt $receipt): void
    {
        $etag = JournalPayload::receipt($receipt->etag, 255);
        $version = JournalPayload::receipt($receipt->versionId, 1024);
        $this->transaction(function (Connection $db) use ($objectId, $receipt, $etag, $version) {
            [$object] = $this->lockObject($db, $objectId);
            $this->require($object->write_state === ObjectWriteState::Intent->value);
            $state = match ($receipt->state) {
                CreateState::Created => ObjectWriteState::Created,
                CreateState::Rejected, CreateState::Failed => ObjectWriteState::Rejected,
                CreateState::Unknown => ObjectWriteState::Unknown,
            };
            $this->require($state === ObjectWriteState::Created || ($etag === null && $version === null));
            $db->table('media_backfill_objects')->where('id', $objectId)->update([
                'write_state' => $state->value, 'create_state' => $receipt->state->value,
                'etag' => $etag, 'version_id' => $version,
                'write_confirmed_at' => $state === ObjectWriteState::Created ? now() : null, 'updated_at' => now(),
            ]);
        });
    }

    /** Metadata only. Never deletes storage; intent/unknown cannot establish cleanup ownership. */
    public function updateCleanup(int $objectId, CleanupState $next): void
    {
        $this->transaction(function (Connection $db) use ($objectId, $next) {
            [$object] = $this->lockObject($db, $objectId, false);
            $previous = CleanupState::from($object->cleanup_state);
            $allowed = match ($previous) {
                CleanupState::NotRequired, CleanupState::Failed, CleanupState::Unknown => [CleanupState::Pending],
                CleanupState::Pending => [CleanupState::Deleted, CleanupState::Failed, CleanupState::Unknown],
                CleanupState::Deleted => [],
            };
            $this->require($object->write_state === ObjectWriteState::Created->value && in_array($next, $allowed, true));
            $db->table('media_backfill_objects')->where('id', $objectId)->update([
                'cleanup_state' => $next->value, 'cleanup_attempted_at' => $next === CleanupState::Pending ? now() : $object->cleanup_attempted_at,
                'cleanup_finished_at' => $next === CleanupState::Deleted ? now() : null, 'updated_at' => now(),
            ]);
        });
    }

    public function finishItem(int $itemId, ApplyResult $result): void
    {
        $this->transaction(function (Connection $db) use ($itemId, $result) {
            $item = $this->lockItem($db, $itemId);
            $this->require($item->phase !== ItemPhase::Finished->value);
            $objects = $db->table('media_backfill_objects')->where('item_id', $itemId)->get();
            $unresolved = $objects->contains(fn ($o) => in_array($o->write_state, ['intent', 'unknown'], true));
            $created = $objects->where('write_state', ObjectWriteState::Created->value);
            $dirty = $created->where('cleanup_state', '!=', CleanupState::Deleted->value);
            $candidate = $item->candidate_manifest_json !== null ? json_decode($item->candidate_manifest_json, true, 16, JSON_THROW_ON_ERROR) : null;
            $allowed = match ($result) {
                ApplyResult::Published => $item->phase === ItemPhase::Writing->value && $objects->isNotEmpty()
                    && $candidate !== null && $objects->count() === count($candidate['variants']) + 1
                    && $objects->count() === $created->where('cleanup_state', CleanupState::NotRequired->value)->count()
                    && $created->where('kind', ObjectKind::Manifest->value)->count() === 1,
                ApplyResult::PublicationUnknown => $unresolved,
                ApplyResult::FailedCompensated => ! $unresolved && $created->isNotEmpty() && $dirty->isEmpty(),
                ApplyResult::FailedCleanupIncomplete => ! $unresolved && $dirty->isNotEmpty()
                    && $dirty->every(fn ($o) => in_array($o->cleanup_state, ['pending', 'failed', 'unknown'], true)),
                ApplyResult::CollisionDetected => ! $unresolved && $dirty->isEmpty()
                    && $objects->where('write_state', ObjectWriteState::Rejected->value)->where('create_state', CreateState::Rejected->value)->isNotEmpty(),
                ApplyResult::Skipped, ApplyResult::ReferenceChanged => $objects->every(fn ($o) => $o->write_state === ObjectWriteState::Planned->value),
                ApplyResult::FailedNoWrites => ! $unresolved && $created->isEmpty(),
            };
            $this->require($allowed);
            $db->table('media_backfill_items')->where('id', $itemId)->update([
                'phase' => ItemPhase::Finished->value, 'apply_result' => $result->value, 'finished_at' => now(), 'updated_at' => now(),
            ]);
        });
    }

    public function heartbeat(string $runId): void
    {
        $this->transaction(function (Connection $db) use ($runId) {
            $this->lockRun($db, $runId);
            $db->table('media_backfill_runs')->where('run_id', $runId)->update(['heartbeat_at' => now(), 'updated_at' => now()]);
        });
    }

    /** @param array<string, int> $summary Counts keyed exclusively by known outcomes/classifications. */
    public function finishRun(string $runId, RunState $state, array $summary = [], ?SafetyError $error = null): void
    {
        $this->require($state !== RunState::Active);
        foreach ($summary as $key => $count) {
            if ((! ApplyResult::tryFrom($key) && ! PreflightClassification::tryFrom($key)) || ! is_int($count) || $count < 0) {
                throw new BackfillSafetyException(SafetyError::InvalidInput);
            }
        }
        $this->transaction(function (Connection $db) use ($runId, $state, $summary, $error) {
            $this->lockRun($db, $runId);
            if ($state === RunState::Completed) {
                $this->require(! $db->table('media_backfill_items')->where('run_id', $runId)
                    ->where(fn ($q) => $q->where('phase', '!=', ItemPhase::Finished->value)->orWhere('apply_result', ApplyResult::PublicationUnknown->value))->exists());
            }
            $db->table('media_backfill_runs')->where('run_id', $runId)->update([
                'state' => $state->value, 'summary_json' => json_encode($summary, JSON_THROW_ON_ERROR),
                'error_code' => $error?->value, 'finished_at' => now(), 'updated_at' => now(),
            ]);
        });
    }

    public function run(string $id): stdClass
    {
        return $this->read('media_backfill_runs', 'run_id', $id);
    }

    public function item(int $id): stdClass
    {
        return $this->read('media_backfill_items', 'id', $id);
    }

    public function object(int $id): stdClass
    {
        return $this->read('media_backfill_objects', 'id', $id);
    }

    public function target(int $id): TargetObject
    {
        $row = $this->object($id);

        return new TargetObject($row->object_key, ObjectKind::from($row->kind), $row->expected_sha256, (int) $row->expected_size, $row->mime_type);
    }

    /** Includes unresolved writes on terminal runs/items. Bounded pages, no recovery mutations. */
    public function unresolvedObjects(int $afterId = 0, int $limit = 100): array
    {
        $this->require($limit > 0 && $limit <= 1000 && $afterId >= 0);

        return $this->safe(fn () => $this->database->connection()->table('media_backfill_objects')->useWritePdo()
            ->where('id', '>', $afterId)->where(fn ($q) => $q->whereIn('write_state', ['intent', 'unknown'])
            ->orWhereIn('cleanup_state', ['pending', 'failed', 'unknown']))->orderBy('id')->limit($limit)->get()->all());
    }

    public function activeRuns(int $limit = 100): array
    {
        $this->require($limit > 0 && $limit <= 1000);

        return $this->safe(fn () => $this->database->connection()->table('media_backfill_runs')->useWritePdo()
            ->where('state', RunState::Active->value)->orderBy('started_at')->orderBy('run_id')->limit($limit)->get()->all());
    }

    public function unfinishedItems(string $runId, int $afterId = 0, int $limit = 100): array
    {
        $this->require($limit > 0 && $limit <= 1000 && $afterId >= 0);

        return $this->safe(fn () => $this->database->connection()->table('media_backfill_items')->useWritePdo()
            ->where('run_id', $runId)->where('id', '>', $afterId)->where('phase', '!=', ItemPhase::Finished->value)
            ->orderBy('id')->limit($limit)->get()->all());
    }

    /** Bounded read-only page of every item belonging to one exact run. */
    public function itemsForRun(string $runId, int $afterId = 0, int $limit = 100): array
    {
        $this->require($this->isCanonicalUuid($runId) && $limit > 0 && $limit <= 1000 && $afterId >= 0);

        return $this->safe(fn () => $this->database->connection()->table('media_backfill_items')->useWritePdo()
            ->where('run_id', $runId)->where('id', '>', $afterId)
            ->orderBy('id')->limit($limit)->get()->all());
    }

    /** Bounded read-only page of every object belonging to one exact item. */
    public function objectsForItem(int $itemId, int $afterId = 0, int $limit = 100): array
    {
        $this->require($itemId > 0 && $limit > 0 && $limit <= 1000 && $afterId >= 0);

        return $this->safe(fn () => $this->database->connection()->table('media_backfill_objects')->useWritePdo()
            ->where('item_id', $itemId)->where('id', '>', $afterId)
            ->orderBy('id')->limit($limit)->get()->all());
    }

    /** Includes unfinished items on terminal runs. Bounded pages, no recovery mutations. */
    public function allUnfinishedItems(int $afterId = 0, int $limit = 100): array
    {
        $this->require($limit > 0 && $limit <= 1000 && $afterId >= 0);

        return $this->safe(fn () => $this->database->connection()->table('media_backfill_items')->useWritePdo()
            ->where('id', '>', $afterId)->where('phase', '!=', ItemPhase::Finished->value)
            ->orderBy('id')->limit($limit)->get()->all());
    }

    /** Bounded unresolved-object page scoped through exact journal parentage. */
    public function unresolvedObjectsForRun(string $runId, int $afterId = 0, int $limit = 100): array
    {
        $this->require($this->isCanonicalUuid($runId) && $limit > 0 && $limit <= 1000 && $afterId >= 0);

        return $this->safe(fn () => $this->database->connection()->table('media_backfill_objects as objects')->useWritePdo()
            ->join('media_backfill_items as items', 'items.id', '=', 'objects.item_id')
            ->where('items.run_id', $runId)->where('objects.id', '>', $afterId)
            ->where(fn ($query) => $query->whereIn('objects.write_state', ['intent', 'unknown'])
                ->orWhereIn('objects.cleanup_state', ['pending', 'failed', 'unknown']))
            ->select('objects.*')->orderBy('objects.id')->limit($limit)->get()->all());
    }

    /** @return array{total_items: int, unfinished_items: int, unresolved_objects: int, ambiguous_writes: int, cleanup_attention: int} */
    public function runEvidenceCounts(string $runId): array
    {
        $this->require($this->isCanonicalUuid($runId));

        return $this->safe(function () use ($runId) {
            $db = $this->database->connection();
            $items = $db->table('media_backfill_items')->useWritePdo()->where('run_id', $runId);
            $objects = fn () => $db->table('media_backfill_objects as objects')->useWritePdo()
                ->join('media_backfill_items as items', 'items.id', '=', 'objects.item_id')
                ->where('items.run_id', $runId);

            return [
                'total_items' => (clone $items)->count(),
                'unfinished_items' => (clone $items)->where('phase', '!=', ItemPhase::Finished->value)->count(),
                'unresolved_objects' => $objects()->where(fn ($query) => $query
                    ->whereIn('objects.write_state', ['intent', 'unknown'])
                    ->orWhereIn('objects.cleanup_state', ['pending', 'failed', 'unknown']))->count(),
                'ambiguous_writes' => $objects()->whereIn('objects.write_state', ['intent', 'unknown'])->count(),
                'cleanup_attention' => $objects()->whereIn('objects.cleanup_state', ['pending', 'failed', 'unknown'])->count(),
            ];
        });
    }

    /** Read-only. The future runner must call this before creating its own active run. */
    public function recoveryBarrier(): RecoveryBarrierState
    {
        return $this->safe(function () {
            $db = $this->database->connection();
            if ($db->getDriverName() !== 'mariadb') {
                throw new BackfillSafetyException(SafetyError::JournalUnavailable);
            }

            return $this->reconciliation()->barrierIsClear($db)
                ? RecoveryBarrierState::Clear
                : RecoveryBarrierState::Blocked;
        });
    }

    /** Early DB-only publisher guard; lockRun() remains the authoritative mutation guard. */
    public function assertRunMutable(string $runId): void
    {
        $this->safe(function () use ($runId) {
            if (! $this->isCanonicalUuid($runId)) {
                throw new BackfillSafetyException(SafetyError::InvalidInput);
            }
            $db = $this->database->connection();
            if ($db->getDriverName() !== 'mariadb') {
                throw new BackfillSafetyException(SafetyError::JournalUnavailable);
            }
            if ($this->reconciliation()->hasRunFootprintOn($db, $runId)) {
                throw new BackfillSafetyException(SafetyError::ReconciliationRequired);
            }
        });
    }

    private function read(string $table, string $column, string|int $id): stdClass
    {
        return $this->safe(function () use ($table, $column, $id) {
            $row = $this->database->connection()->table($table)->useWritePdo()->where($column, $id)->first();
            $this->require($row !== null);

            return $row;
        });
    }

    private function lockRun(Connection $db, string $id, bool $active = true): stdClass
    {
        $run = $db->table('media_backfill_runs')->where('run_id', $id)->lockForUpdate()->first();
        $this->require($run !== null && $run->mode === JournalMode::Apply->value);
        if ($this->reconciliation()->hasRunFootprintOn($db, $id)) {
            throw new BackfillSafetyException(SafetyError::ReconciliationRequired);
        }
        $this->require(! $active || $run->state === RunState::Active->value);

        return $run;
    }

    private function lockItem(Connection $db, int $id, bool $active = true): stdClass
    {
        $runId = $db->table('media_backfill_items')->where('id', $id)->value('run_id');
        $this->require($runId !== null);
        $this->lockRun($db, $runId, $active);
        $item = $db->table('media_backfill_items')->where('id', $id)->lockForUpdate()->first();
        $this->require($item !== null);

        return $item;
    }

    private function lockObject(Connection $db, int $id, bool $active = true): array
    {
        $itemId = $db->table('media_backfill_objects')->where('id', $id)->value('item_id');
        $this->require($itemId !== null);
        $item = $this->lockItem($db, (int) $itemId, $active);
        $object = $db->table('media_backfill_objects')->where('id', $id)->lockForUpdate()->first();
        $this->require($object !== null && (! $active || $item->phase !== ItemPhase::Finished->value));

        return [$object, $item];
    }

    private function runSelection(stdClass $run): ApplyRunSelection
    {
        $options = json_decode($run->options_json, true, 4, JSON_THROW_ON_ERROR);
        $upperBounds = json_decode($run->upper_bounds_json, true, 4, JSON_THROW_ON_ERROR);
        $this->require(is_array($options) && array_keys($options) === ['domain', 'after_id', 'limit']
            && is_string($options['domain']) && is_int($options['after_id']) && is_int($options['limit'])
            && is_array($upperBounds) && array_keys($upperBounds) === [$options['domain']]
            && is_int($upperBounds[$options['domain']]));
        $domain = ManagedMediaDomain::tryFrom($options['domain']);
        $this->require($domain !== null);

        return new ApplyRunSelection($domain, $options['after_id'], $options['limit'], $upperBounds[$options['domain']]);
    }

    private function checkpoint(stdClass $run, ApplyRunSelection $selection): int
    {
        $checkpoints = json_decode($run->checkpoints_json, true, 4, JSON_THROW_ON_ERROR);
        $this->require(is_array($checkpoints) && array_keys($checkpoints) === [$selection->domain->value]
            && is_int($checkpoints[$selection->domain->value]));
        $checkpoint = $checkpoints[$selection->domain->value];
        $this->require($checkpoint >= $selection->afterId
            && ($checkpoint <= $selection->upperBound || $checkpoint === $selection->afterId));

        return $checkpoint;
    }

    private function transaction(Closure $callback): mixed
    {
        return $this->safe(function () use ($callback) {
            $db = $this->database->connection();
            if ($db->getDriverName() !== 'mariadb') {
                throw new BackfillSafetyException(SafetyError::JournalUnavailable);
            }
            if ($db->transactionLevel() !== 0 || $db->getPdo()->inTransaction()) {
                throw new BackfillSafetyException(SafetyError::AmbientTransaction);
            }

            // Explicit lifecycle: Laravel transaction(..., 1) decrements its counter on a
            // committing-event failure without rolling back the still-open PDO transaction.
            // Keep the level until rollback succeeds; never leave uncommitted intent visible
            // to a later call on the same application session.
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
        });
    }

    private function safe(Closure $callback): mixed
    {
        try {
            return $callback();
        } catch (BackfillSafetyException $error) {
            throw $error;
        } catch (Throwable) {
            throw new BackfillSafetyException(SafetyError::JournalUnavailable);
        }
    }

    private function require(bool $condition): void
    {
        if (! $condition) {
            throw new BackfillSafetyException(SafetyError::IllegalTransition);
        }
    }

    private function reconciliation(): ReconciliationStateValidator
    {
        return $this->reconciliation ?? app(ReconciliationStateValidator::class);
    }

    private function isCanonicalUuid(string $value): bool
    {
        return preg_match('/\A[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}\z/D', $value) === 1;
    }
}
