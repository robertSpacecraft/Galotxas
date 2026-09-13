<?php

namespace App\Services\Media\Backfill;

use App\Services\Media\Backfill\Safety\AdvisoryLockHandle;
use App\Services\Media\Backfill\Safety\ApplyJournal;
use App\Services\Media\Backfill\Safety\ApplyMaintenanceGuard;
use App\Services\Media\Backfill\Safety\ApplyResult;
use App\Services\Media\Backfill\Safety\BackfillSafetyException;
use App\Services\Media\Backfill\Safety\CleanupState;
use App\Services\Media\Backfill\Safety\CreateReceipt;
use App\Services\Media\Backfill\Safety\CreateState;
use App\Services\Media\Backfill\Safety\ItemPhase;
use App\Services\Media\Backfill\Safety\JournaledObjectWriter;
use App\Services\Media\Backfill\Safety\JournalMode;
use App\Services\Media\Backfill\Safety\JournalPayload;
use App\Services\Media\Backfill\Safety\ObjectKind;
use App\Services\Media\Backfill\Safety\ObjectWriteState;
use App\Services\Media\Backfill\Safety\RunState;
use App\Services\Media\Backfill\Safety\SafetyError;
use App\Services\Media\Backfill\Safety\StorageIdentity;
use App\Services\Media\Backfill\Safety\TargetObject;
use App\Services\Media\ManifestImage;
use App\Services\Media\PreparedResponsiveDerivatives;
use App\Services\Media\ResponsiveManifest;
use App\Services\Media\ResponsiveMediaKeys;
use Illuminate\Database\DatabaseManager;
use stdClass;
use Throwable;

/** Publishes one already-journaled candidate. No run lifecycle, checkpointing or deletion. */
class ApplyItemPublisher
{
    public function __construct(
        private readonly ApplyJournal $journal,
        private readonly ManagedMediaReferenceRegistry $registry,
        private readonly ResponsiveBackfillPreflight $preflight,
        private readonly ResponsiveMediaInspector $inspector,
        private readonly ResponsiveMediaKeys $keys,
        private readonly JournaledObjectWriter $writer,
        private readonly ApplyMaintenanceGuard $maintenance,
        private readonly DatabaseManager $database,
    ) {}

    public function publish(string $runId, int $itemId, AdvisoryLockHandle $lock): ApplyResult
    {
        $this->journal->assertRunMutable($runId);
        [$item, $domain, $snapshotManifest] = $this->candidate($runId, $itemId);
        $this->guardEnvironment($runId, $itemId, $lock, []);

        try {
            $reference = $this->registry->find($domain, (int) $item->entity_id);
        } catch (Throwable) {
            return $this->finish($itemId, ApplyResult::FailedNoWrites);
        }
        if ($reference === null || ! $this->matchesSnapshotIdentity($reference, $item)) {
            return $this->finish($itemId, ApplyResult::ReferenceChanged);
        }

        try {
            $fresh = $this->preflight->inspect($reference);
        } catch (Throwable) {
            return $this->finish($itemId, ApplyResult::FailedNoWrites);
        }
        if (! $this->sameReference($reference, $fresh->reference)) {
            return $this->finish($itemId, ApplyResult::ReferenceChanged);
        }
        if ($fresh->classification !== PreflightClassification::LegacyBackfillable || $fresh->prepared === null) {
            return $this->finish($itemId, $this->firstPreflightFailure($fresh->classification));
        }
        $prepared = $fresh->prepared;
        $candidateJson = $prepared->manifest->toJson();
        if (! hash_equals($item->source_sha256, $prepared->masterSha256)
            || $candidateJson !== $item->candidate_manifest_json
            || ! hash_equals($item->candidate_manifest_sha256, hash('sha256', $candidateJson))) {
            return $this->finish($itemId, ApplyResult::FailedNoWrites);
        }

        [$variants, $manifest] = $this->planTargets($itemId, $item, $snapshotManifest, $prepared);
        $barrierFailure = $this->firstPublicationBarrier($reference, $item, $snapshotManifest);
        if ($barrierFailure !== null) {
            return $this->finish($itemId, $barrierFailure);
        }
        $this->guardEnvironment($runId, $itemId, $lock, []);
        $this->assertAllPlanned($itemId, [...$variants, $manifest]);
        $this->journal->markRevalidated($itemId);
        $this->assertReadyForFirstWrite($itemId, [...$variants, $manifest]);

        $createdVariantIds = [];
        foreach ($variants as $variant) {
            $receipt = $this->create($itemId, $variant, $lock, $createdVariantIds);
            if ($receipt instanceof ApplyResult) {
                return $receipt;
            }
            if ($receipt->state !== CreateState::Created) {
                return $this->knownReceiptFailure($itemId, $receipt->state, $createdVariantIds);
            }
            $this->assertCreatedVariant($itemId, $variant);
            $createdVariantIds[] = $variant['id'];
        }

        $barrierFailure = $this->secondPublicationBarrier(
            $runId,
            $itemId,
            $reference,
            $item,
            $snapshotManifest,
            $variants,
            $manifest,
        );
        if ($barrierFailure !== null) {
            return $createdVariantIds === []
                ? $this->finish($itemId, $barrierFailure)
                : $this->finishWithPendingCleanup($itemId, $createdVariantIds);
        }
        $this->guardEnvironment($runId, $itemId, $lock, $createdVariantIds);

        $receipt = $this->create($itemId, $manifest, $lock, $createdVariantIds);
        if ($receipt instanceof ApplyResult) {
            return $receipt;
        }
        if ($receipt->state !== CreateState::Created) {
            return $this->knownReceiptFailure($itemId, $receipt->state, $createdVariantIds);
        }

        return $this->finish($itemId, ApplyResult::Published);
    }

    /** @return array{stdClass, ManagedMediaDomain, ResponsiveManifest} */
    private function candidate(string $runId, int $itemId): array
    {
        if ($itemId < 1) {
            throw new BackfillSafetyException(SafetyError::InvalidInput);
        }
        $run = $this->journal->run($runId);
        $item = $this->journal->item($itemId);
        $valid = $run->mode === JournalMode::Apply->value
            && $run->state === RunState::Active->value
            && $item->run_id === $runId
            && $item->phase === ItemPhase::Inspected->value
            && $item->preflight_classification === PreflightClassification::LegacyBackfillable->value
            && is_string($item->master_key)
            && is_string($item->source_sha256)
            && is_string($item->candidate_manifest_json)
            && is_string($item->candidate_manifest_sha256)
            && is_string($item->manifest_key)
            && is_string($run->storage_identity_hash)
            && preg_match('/\A[0-9a-f]{64}\z/', $run->storage_identity_hash) === 1;
        $domain = is_string($item->domain) ? ManagedMediaDomain::tryFrom($item->domain) : null;
        if (! $valid || $domain === null || (int) $item->entity_id < 1
            || hash('sha256', $item->master_key) !== $item->master_key_hash
            || hash('sha256', $item->candidate_manifest_json) !== $item->candidate_manifest_sha256) {
            throw new BackfillSafetyException(SafetyError::IllegalTransition);
        }
        try {
            JournalPayload::sha256($item->source_sha256);
            JournalPayload::sha256($item->candidate_manifest_sha256);
            $manifest = ResponsiveManifest::fromJson(
                $item->candidate_manifest_json,
                $item->master_key,
                $item->manifest_key,
                $this->keys,
            );
        } catch (Throwable) {
            throw new BackfillSafetyException(SafetyError::IllegalTransition);
        }
        if ($manifest->schemaVersion !== 2 || $manifest->profile !== $domain->profile()
            || $manifest->policy !== $domain->policy()) {
            throw new BackfillSafetyException(SafetyError::IllegalTransition);
        }

        return [$item, $domain, $manifest];
    }

    private function firstPreflightFailure(PreflightClassification $classification): ApplyResult
    {
        return in_array($classification, [
            PreflightClassification::ExcludedNull,
            PreflightClassification::ExcludedDeleted,
            PreflightClassification::InvalidReference,
            PreflightClassification::ReferenceConflict,
            PreflightClassification::MetadataMismatch,
        ], true) ? ApplyResult::ReferenceChanged : ApplyResult::FailedNoWrites;
    }

    /** @return array{list<array{id: int, target: TargetObject, bytes: string, descriptor: ManifestImage}>, array{id: int, target: TargetObject, bytes: string}} */
    private function planTargets(
        int $itemId,
        stdClass $item,
        ResponsiveManifest $snapshotManifest,
        PreparedResponsiveDerivatives $prepared,
    ): array {
        if (count($prepared->variants) !== count($snapshotManifest->variants)) {
            throw new BackfillSafetyException(SafetyError::IllegalTransition);
        }
        $variants = [];
        foreach ($snapshotManifest->variants as $index => $descriptor) {
            $image = $prepared->variants[$index];
            if ($image->width !== $descriptor->width || $image->height !== $descriptor->height
                || $image->size !== $descriptor->size || $image->mimeType !== $descriptor->mimeType) {
                throw new BackfillSafetyException(SafetyError::IllegalTransition);
            }
            $target = new TargetObject(
                $descriptor->key,
                ObjectKind::Variant,
                hash('sha256', $image->bytes),
                $image->size,
                $image->mimeType,
            );
            $target->validateBytes($image->bytes);
            $variants[] = [
                'id' => $this->journal->planObject($itemId, $target),
                'target' => $target,
                'bytes' => $image->bytes,
                'descriptor' => $descriptor,
            ];
        }
        $manifestTarget = new TargetObject(
            $item->manifest_key,
            ObjectKind::Manifest,
            $item->candidate_manifest_sha256,
            strlen($item->candidate_manifest_json),
            'application/json',
        );
        $manifestTarget->validateBytes($item->candidate_manifest_json);
        $manifest = [
            'id' => $this->journal->planObject($itemId, $manifestTarget),
            'target' => $manifestTarget,
            'bytes' => $item->candidate_manifest_json,
        ];

        return [$variants, $manifest];
    }

    private function firstPublicationBarrier(
        ManagedMediaReference $accepted,
        stdClass $item,
        ResponsiveManifest $manifest,
    ): ?ApplyResult {
        $referenceFailure = $this->referenceAndMasterBarrier($accepted, $item, $manifest);
        if ($referenceFailure !== null) {
            return $referenceFailure;
        }
        try {
            if ($this->inspector->inspectManifest($item->master_key, $manifest->profile, $manifest->policy)->state
                !== ManifestInspectionState::Missing) {
                return ApplyResult::FailedNoWrites;
            }
            foreach ($this->inspector->inspectTargets($item->master_key, $manifest->profile) as $target) {
                if ($target->state !== ObjectInspectionState::Missing) {
                    return ApplyResult::FailedNoWrites;
                }
            }
        } catch (Throwable) {
            return ApplyResult::FailedNoWrites;
        }

        return null;
    }

    /**
     * @param  list<array{id: int, target: TargetObject, bytes: string, descriptor: ManifestImage}>  $variants
     * @param  array{id: int, target: TargetObject, bytes: string}  $manifestTarget
     */
    private function secondPublicationBarrier(
        string $runId,
        int $itemId,
        ManagedMediaReference $accepted,
        stdClass $item,
        ResponsiveManifest $manifest,
        array $variants,
        array $manifestTarget,
    ): ?ApplyResult {
        $currentItem = $this->journal->item($itemId);
        if ($currentItem->run_id !== $runId || ! in_array($currentItem->phase, [ItemPhase::Revalidated->value, ItemPhase::Writing->value], true)) {
            throw new BackfillSafetyException(SafetyError::IllegalTransition);
        }
        $referenceFailure = $this->referenceAndMasterBarrier($accepted, $item, $manifest);
        if ($referenceFailure !== null) {
            return $referenceFailure;
        }
        try {
            $expectedKeys = array_map(fn (array $variant) => $variant['target']->key, $variants);
            foreach ($this->inspector->inspectTargets($item->master_key, $manifest->profile) as $key => $target) {
                $expectedState = in_array($key, $expectedKeys, true)
                    ? ObjectInspectionState::Present
                    : ObjectInspectionState::Missing;
                if ($target->state !== $expectedState) {
                    return ApplyResult::FailedNoWrites;
                }
            }
            foreach ($variants as $variant) {
                $this->assertCreatedVariant($itemId, $variant);
                $object = $this->inspector->inspectVariant($variant['descriptor'], $item->master_key, $manifest->profile);
                if ($object->state !== ObjectInspectionState::Present
                    || ! $this->inspector->matchesDescriptor($object->bytes, $variant['descriptor'])
                    || ! hash_equals($variant['target']->sha256, hash('sha256', $object->bytes))) {
                    return ApplyResult::FailedNoWrites;
                }
            }
            if ($this->inspector->inspectManifest($item->master_key, $manifest->profile, $manifest->policy)->state
                !== ManifestInspectionState::Missing) {
                return ApplyResult::FailedNoWrites;
            }
        } catch (BackfillSafetyException $error) {
            throw $error;
        } catch (Throwable) {
            return ApplyResult::FailedNoWrites;
        }
        $this->assertPlannedTarget($itemId, $manifestTarget);

        return null;
    }

    private function referenceAndMasterBarrier(
        ManagedMediaReference $accepted,
        stdClass $item,
        ResponsiveManifest $manifest,
    ): ?ApplyResult {
        try {
            $current = $this->registry->find($accepted->domain, $accepted->id);
        } catch (Throwable) {
            return ApplyResult::FailedNoWrites;
        }
        if ($current === null || ! $this->sameReference($accepted, $current)) {
            return ApplyResult::ReferenceChanged;
        }
        try {
            $identity = $this->registry->identity($current);
            $owners = $this->registry->liveOwners([$current]);
        } catch (Throwable) {
            return ApplyResult::FailedNoWrites;
        }
        if ($identity === null || count($owners[$identity] ?? []) !== 1
            || ! $this->sameReference($current, $owners[$identity][0])) {
            return ApplyResult::ReferenceChanged;
        }
        try {
            $master = $this->inspector->inspectMaster($item->master_key, $manifest->profile);
        } catch (Throwable) {
            return ApplyResult::FailedNoWrites;
        }
        if ($master->state !== ObjectInspectionState::Present
            || ! hash_equals($item->source_sha256, hash('sha256', $master->bytes))) {
            return ApplyResult::FailedNoWrites;
        }

        return null;
    }

    private function matchesSnapshotIdentity(ManagedMediaReference $reference, stdClass $item): bool
    {
        return $reference->domain->value === $item->domain
            && $reference->id === (int) $item->entity_id
            && $reference->masterKey === $item->master_key;
    }

    private function sameReference(ManagedMediaReference $left, ManagedMediaReference $right): bool
    {
        return $left->domain === $right->domain
            && $left->id === $right->id
            && $left->masterKey === $right->masterKey
            && $left->deleted === $right->deleted
            && $left->width === $right->width
            && $left->height === $right->height;
    }

    /** @param list<array{id: int, target: TargetObject, bytes: string, descriptor?: ManifestImage}> $targets */
    private function assertAllPlanned(int $itemId, array $targets): void
    {
        foreach ($targets as $target) {
            $this->assertPlannedTarget($itemId, $target);
        }
    }

    /** @param array{id: int, target: TargetObject, bytes: string, descriptor?: ManifestImage} $target */
    private function assertPlannedTarget(int $itemId, array $target): void
    {
        $object = $this->journal->object($target['id']);
        if ((int) $object->item_id !== $itemId
            || $object->object_key !== $target['target']->key
            || $object->kind !== $target['target']->kind->value
            || $object->expected_sha256 !== $target['target']->sha256
            || (int) $object->expected_size !== $target['target']->size
            || $object->mime_type !== $target['target']->mimeType
            || $object->write_state !== ObjectWriteState::Planned->value
            || $object->create_state !== null
            || $object->cleanup_state !== CleanupState::NotRequired->value) {
            throw new BackfillSafetyException(SafetyError::IllegalTransition);
        }
    }

    /** @param list<array{id: int, target: TargetObject, bytes: string, descriptor?: ManifestImage}> $targets */
    private function assertReadyForFirstWrite(int $itemId, array $targets): void
    {
        if ($this->journal->item($itemId)->phase !== ItemPhase::Revalidated->value) {
            throw new BackfillSafetyException(SafetyError::IllegalTransition);
        }
        $this->assertAllPlanned($itemId, $targets);
    }

    /** @param array{id: int, target: TargetObject, bytes: string, descriptor: ManifestImage} $variant */
    private function assertCreatedVariant(int $itemId, array $variant): void
    {
        $object = $this->journal->object($variant['id']);
        if ((int) $object->item_id !== $itemId
            || $object->kind !== ObjectKind::Variant->value
            || $object->object_key !== $variant['target']->key
            || $object->expected_sha256 !== $variant['target']->sha256
            || (int) $object->expected_size !== $variant['target']->size
            || $object->mime_type !== $variant['target']->mimeType
            || $object->write_state !== ObjectWriteState::Created->value
            || $object->create_state !== CreateState::Created->value
            || $object->cleanup_state !== CleanupState::NotRequired->value) {
            throw new BackfillSafetyException(SafetyError::IllegalTransition);
        }
    }

    /**
     * @param  array{id: int, target: TargetObject, bytes: string, descriptor?: ManifestImage}  $target
     * @param  list<int>  $createdVariantIds
     */
    private function create(
        int $itemId,
        array $target,
        AdvisoryLockHandle $lock,
        array $createdVariantIds,
    ): CreateReceipt|ApplyResult {
        try {
            return $this->writer->create($target['id'], $target['bytes'], $lock);
        } catch (BackfillSafetyException $error) {
            if ($error->reason === SafetyError::PublicationUnknown) {
                return $this->publicationUnknown($itemId);
            }
            if ($this->isEnvironmentFailure($error)) {
                $object = $this->journal->object($target['id']);
                if (in_array($object->write_state, [ObjectWriteState::Intent->value, ObjectWriteState::Unknown->value], true)) {
                    return $this->publicationUnknown($itemId);
                }
                $this->finishAfterBarrierFailure($itemId, $createdVariantIds);
            }

            throw $error;
        }
    }

    /** @param list<int> $createdVariantIds */
    private function guardEnvironment(
        string $runId,
        int $itemId,
        AdvisoryLockHandle $lock,
        array $createdVariantIds,
    ): void {
        try {
            $run = $this->journal->run($runId);
            if ($run->mode !== JournalMode::Apply->value || $run->state !== RunState::Active->value) {
                throw new BackfillSafetyException(SafetyError::IllegalTransition);
            }
            $identity = StorageIdentity::current($this->database);
            if (! hash_equals($identity->hash, $run->storage_identity_hash)
                || ! hash_equals($identity->hash, $lock->identity->hash)) {
                throw new BackfillSafetyException(SafetyError::IdentityMismatch);
            }
            $this->maintenance->assertAllowed();
            $lock->assertOwned();
        } catch (BackfillSafetyException $error) {
            if ($this->isEnvironmentFailure($error)) {
                $this->finishAfterBarrierFailure($itemId, $createdVariantIds);
            }

            throw $error;
        }
    }

    private function isEnvironmentFailure(BackfillSafetyException $error): bool
    {
        return in_array($error->reason, [
            SafetyError::MaintenanceRequired,
            SafetyError::LockLost,
            SafetyError::UnsupportedStorage,
            SafetyError::IdentityMismatch,
            SafetyError::InvalidInput,
        ], true);
    }

    /** @param list<int> $createdVariantIds */
    private function finishAfterBarrierFailure(int $itemId, array $createdVariantIds): void
    {
        if ($createdVariantIds === []) {
            $this->journal->finishItem($itemId, ApplyResult::FailedNoWrites);

            return;
        }
        $this->finishWithPendingCleanup($itemId, $createdVariantIds);
    }

    /** @param list<int> $createdVariantIds */
    private function knownReceiptFailure(int $itemId, CreateState $state, array $createdVariantIds): ApplyResult
    {
        if ($state === CreateState::Unknown) {
            return $this->publicationUnknown($itemId);
        }
        if ($createdVariantIds !== []) {
            return $this->finishWithPendingCleanup($itemId, $createdVariantIds);
        }

        return $this->finish(
            $itemId,
            $state === CreateState::Rejected ? ApplyResult::CollisionDetected : ApplyResult::FailedNoWrites,
        );
    }

    /** @param list<int> $createdVariantIds */
    private function finishWithPendingCleanup(int $itemId, array $createdVariantIds): ApplyResult
    {
        foreach ($createdVariantIds as $objectId) {
            $object = $this->journal->object($objectId);
            if ((int) $object->item_id !== $itemId
                || $object->kind !== ObjectKind::Variant->value
                || $object->write_state !== ObjectWriteState::Created->value
                || $object->create_state !== CreateState::Created->value
                || $object->cleanup_state !== CleanupState::NotRequired->value) {
                throw new BackfillSafetyException(SafetyError::IllegalTransition);
            }
            $this->journal->updateCleanup($objectId, CleanupState::Pending);
        }

        return $this->finish($itemId, ApplyResult::FailedCleanupIncomplete);
    }

    private function publicationUnknown(int $itemId): ApplyResult
    {
        try {
            return $this->finish($itemId, ApplyResult::PublicationUnknown);
        } catch (Throwable) {
            throw new BackfillSafetyException(SafetyError::PublicationUnknown);
        }
    }

    private function finish(int $itemId, ApplyResult $result): ApplyResult
    {
        $this->journal->finishItem($itemId, $result);

        return $result;
    }
}
