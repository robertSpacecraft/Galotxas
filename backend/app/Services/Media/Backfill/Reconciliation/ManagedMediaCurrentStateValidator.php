<?php

namespace App\Services\Media\Backfill\Reconciliation;

use App\Services\Media\Backfill\ManagedMediaDomain;
use App\Services\Media\Backfill\ManagedMediaReference;
use App\Services\Media\Backfill\ManagedMediaReferenceRegistry;
use App\Services\Media\Backfill\ObjectInspectionState;
use App\Services\Media\Backfill\ResponsiveMediaInspector;
use App\Services\Media\Backfill\Safety\StorageIdentity;
use App\Services\Media\ResponsiveManifest;
use stdClass;
use Throwable;

/** Shared read-only revalidation of the exact current domain owner, reference and master. */
final class ManagedMediaCurrentStateValidator
{
    public function __construct(
        private readonly ManagedMediaReferenceRegistry $registry,
        private readonly ResponsiveMediaInspector $inspector,
        private readonly StorageObservationCapability $capability,
    ) {}

    public function validate(
        stdClass $item,
        ResponsiveManifest $candidate,
        StorageIdentity $identity,
        ReconciliationBackendMode $backendMode,
    ): ManagedMediaCurrentState {
        $domain = is_string($item->domain ?? null) ? ManagedMediaDomain::tryFrom($item->domain) : null;
        $entityId = (int) ($item->entity_id ?? 0);
        $masterKey = $item->master_key ?? null;
        if ($domain === null || $entityId < 1 || ! is_string($masterKey)
            || $candidate->profile !== $domain->profile() || $candidate->policy !== $domain->policy()
            || $candidate->master->key !== $masterKey) {
            throw new OperationalEvidenceException(ReconciliationBlockReason::DomainRevalidationFailed);
        }

        try {
            $this->capability->disk($identity, $backendMode);
        } catch (Throwable) {
            throw new OperationalEvidenceException(ReconciliationBlockReason::StorageObservationUntrusted);
        }
        try {
            $current = $this->registry->find($domain, $entityId);
        } catch (Throwable) {
            throw new OperationalEvidenceException(ReconciliationBlockReason::DomainRevalidationFailed);
        }
        if ($current === null || $current->deleted || ! $this->matchesItem($current, $item)
            || ! $this->metadataIsCompatible($current, $candidate)) {
            throw new OperationalEvidenceException(ReconciliationBlockReason::DomainRevalidationFailed);
        }

        try {
            $referenceIdentity = $this->registry->identity($current);
            $owners = $this->registry->liveOwners([$current]);
        } catch (Throwable) {
            throw new OperationalEvidenceException(ReconciliationBlockReason::DomainRevalidationFailed);
        }
        $owner = $referenceIdentity === null ? null : ($owners[$referenceIdentity][0] ?? null);
        if ($referenceIdentity === null || count($owners[$referenceIdentity] ?? []) !== 1
            || ! $owner instanceof ManagedMediaReference || ! $this->sameReference($current, $owner)) {
            throw new OperationalEvidenceException(ReconciliationBlockReason::DomainRevalidationFailed);
        }

        try {
            $master = $this->inspector->inspectMaster($masterKey, $candidate->profile);
        } catch (Throwable) {
            throw new OperationalEvidenceException(ReconciliationBlockReason::StorageObservationUntrusted);
        }
        if ($master->state === ObjectInspectionState::InspectionFailed) {
            throw new OperationalEvidenceException(ReconciliationBlockReason::StorageObservationUntrusted);
        }
        $sourceSha256 = $item->source_sha256 ?? null;
        if ($master->state !== ObjectInspectionState::Present || ! is_string($master->bytes)
            || ! is_string($sourceSha256)
            || ! hash_equals($sourceSha256, hash('sha256', $master->bytes))
            || ! $this->inspector->matchesDescriptor($master->bytes, $candidate->master)) {
            throw new OperationalEvidenceException(ReconciliationBlockReason::EvidenceMismatch);
        }

        try {
            $this->capability->disk($identity, $backendMode);
        } catch (Throwable) {
            throw new OperationalEvidenceException(ReconciliationBlockReason::StorageObservationUntrusted);
        }

        return new ManagedMediaCurrentState(
            hash('sha256', $referenceIdentity),
            hash('sha256', $masterKey),
            hash('sha256', $master->bytes),
            $domain->value,
            $entityId,
        );
    }

    private function matchesItem(ManagedMediaReference $reference, stdClass $item): bool
    {
        return $reference->domain->value === ($item->domain ?? null)
            && $reference->id === (int) ($item->entity_id ?? 0)
            && $reference->masterKey === ($item->master_key ?? null);
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

    private function metadataIsCompatible(ManagedMediaReference $reference, ResponsiveManifest $candidate): bool
    {
        if ($reference->domain->metadataPrefix() === null) {
            return true;
        }
        foreach (['width', 'height'] as $axis) {
            $value = $reference->$axis;
            if ($value === null && $reference->domain === ManagedMediaDomain::News) {
                continue;
            }
            if (! is_int($value) || $value < 1 || $value !== $candidate->master->$axis) {
                return false;
            }
        }

        return true;
    }
}
