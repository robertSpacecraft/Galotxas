<?php

namespace App\Services\Media\Backfill;

use App\Services\Media\Exceptions\InvalidStoredMaster;
use App\Services\Media\ExistingMasterPreparer;
use Throwable;

class ResponsiveBackfillPreflight
{
    public function __construct(
        private readonly ManagedMediaReferenceRegistry $registry,
        private readonly ResponsiveMediaInspector $inspector,
        private readonly ExistingMasterPreparer $preparer,
    ) {}

    public function inspect(ManagedMediaReference $reference): PreflightResult
    {
        return $this->inspectBatch([$reference])[0];
    }

    /** @param list<ManagedMediaReference> $references
     * @return list<PreflightResult>
     */
    public function inspectBatch(array $references): array
    {
        try {
            $owners = $this->registry->liveOwners($references);
            $ownershipFailed = false;
        } catch (Throwable) {
            $owners = [];
            $ownershipFailed = true;
        }

        return array_map(fn (ManagedMediaReference $reference) => $this->classify($reference, $owners, $ownershipFailed), $references);
    }

    private function classify(ManagedMediaReference $reference, array $owners, bool $ownershipFailed): PreflightResult
    {
        $result = fn (PreflightClassification $classification, array $reasons = [], array $keys = []) => new PreflightResult($reference, $classification, $reasons, $keys);
        if ($reference->deleted) {
            return $result(PreflightClassification::ExcludedDeleted);
        }
        if ($reference->masterKey === null && $reference->domain->nullable()) {
            return $result(PreflightClassification::ExcludedNull);
        }
        $identity = $this->registry->identity($reference);
        if ($identity === null) {
            return $result(PreflightClassification::InvalidReference, [InspectionReason::InvalidReference]);
        }
        if ($ownershipFailed) {
            return $result(PreflightClassification::InspectionFailed, [InspectionReason::TransportError]);
        }
        if (count($owners[$identity] ?? []) > 1) {
            return $result(PreflightClassification::ReferenceConflict, [InspectionReason::SharedIdentity]);
        }
        $owner = $owners[$identity][0] ?? null;
        if ($owner === null || $owner->domain !== $reference->domain || $owner->id !== $reference->id || $owner->masterKey !== $reference->masterKey) {
            return $result(PreflightClassification::InvalidReference, [InspectionReason::IdentityMismatch]);
        }
        $profile = $reference->domain->profile();
        $policy = $reference->domain->policy();
        $master = $this->inspector->inspectMaster($reference->masterKey, $profile);
        if ($master->state === ObjectInspectionState::Missing) {
            return $result(PreflightClassification::MasterMissing, [InspectionReason::ObjectMissing]);
        }
        if ($master->state === ObjectInspectionState::InspectionFailed) {
            return $result(PreflightClassification::InspectionFailed, [$master->reason]);
        }
        try {
            $prepared = $this->preparer->prepare($reference->masterKey, $master->bytes, $profile, $policy);
        } catch (InvalidStoredMaster $exception) {
            return $result($exception->reason === InspectionReason::EncodingFailed
                ? PreflightClassification::InspectionFailed : PreflightClassification::MasterUnprocessable, [$exception->reason]);
        }
        $manifestResult = $this->inspector->inspectManifest($reference->masterKey, $profile, $policy);
        if ($manifestResult->state === ManifestInspectionState::InspectionFailed) {
            return $result(PreflightClassification::InspectionFailed, [$manifestResult->reason]);
        }
        $targets = $this->inspector->inspectTargets($reference->masterKey, $profile);
        $present = array_keys(array_filter($targets, fn (ObjectInspection $object) => $object->state === ObjectInspectionState::Present));
        $failed = array_filter($targets, fn (ObjectInspection $object) => $object->state === ObjectInspectionState::InspectionFailed);
        if ($manifestResult->state === ManifestInspectionState::Invalid) {
            return $result(PreflightClassification::ManifestInvalid,
                [$manifestResult->reason, ...($present === [] ? [] : [InspectionReason::ExistingVariantCollision]),
                    ...array_map(fn (ObjectInspection $object) => $object->reason, array_values($failed))], $present);
        }
        $manifest = $manifestResult->manifest;
        $incomplete = [];
        if ($manifest !== null) {
            if (! $this->inspector->matchesDescriptor($master->bytes, $manifest->master)) {
                $incomplete[] = InspectionReason::DescriptorMismatch;
            }
            if (array_column($manifest->variants, 'width') !== array_column($prepared->variants, 'width')) {
                $incomplete[] = InspectionReason::IncompleteCandidates;
            }
            foreach ($manifest->variants as $variant) {
                $object = $this->inspector->inspectVariant($variant, $reference->masterKey, $profile);
                if ($object->state === ObjectInspectionState::InspectionFailed) {
                    $failed[$variant->key] = $object;
                } elseif ($object->state === ObjectInspectionState::Missing) {
                    $incomplete[] = InspectionReason::ObjectMissing;
                } elseif (! $this->inspector->matchesDescriptor($object->bytes, $variant)) {
                    $incomplete[] = InspectionReason::DescriptorMismatch;
                }
            }
        }
        if ($incomplete !== []) {
            return $result(PreflightClassification::ResponsiveIncomplete, array_values(array_unique([
                ...$incomplete, ...array_map(fn (ObjectInspection $object) => $object->reason, array_values($failed)),
            ], SORT_REGULAR)), $present);
        }
        if ($failed !== []) {
            return $result(PreflightClassification::InspectionFailed, array_values(array_map(fn (ObjectInspection $object) => $object->reason, $failed)), $present);
        }
        $residues = array_values(array_diff($present, $manifest === null ? [] : array_column($manifest->variants, 'key')));
        if ($residues !== []) {
            return $result($manifest === null ? PreflightClassification::PartialCollision : PreflightClassification::ResponsiveIncomplete,
                [InspectionReason::ExistingVariantCollision], $residues);
        }
        if ($reference->domain->metadataPrefix() !== null) {
            foreach (['width', 'height'] as $axis) {
                $value = $reference->$axis;
                if ($value === null && $reference->domain === ManagedMediaDomain::News) {
                    continue;
                }
                if (! is_int($value) || $value < 1 || $value !== $prepared->master->$axis) {
                    return $result(PreflightClassification::MetadataMismatch, [InspectionReason::MetadataMismatch]);
                }
            }
        }

        return new PreflightResult($reference, $manifest === null ? PreflightClassification::LegacyBackfillable : PreflightClassification::ResponsiveOk,
            prepared: $manifest === null ? $prepared : null);
    }
}
