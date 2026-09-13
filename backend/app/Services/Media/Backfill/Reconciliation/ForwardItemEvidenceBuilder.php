<?php

namespace App\Services\Media\Backfill\Reconciliation;

use App\Services\Media\Backfill\ManagedMediaDomain;
use App\Services\Media\Backfill\ObjectInspectionState;
use App\Services\Media\Backfill\ResponsiveMediaInspector;
use App\Services\Media\Backfill\Safety\CleanupState;
use App\Services\Media\Backfill\Safety\ObjectKind;
use App\Services\Media\Backfill\Safety\StorageIdentity;
use App\Services\Media\Backfill\Safety\TargetObject;
use App\Services\Media\ImageFormat;
use App\Services\Media\ManifestImage;
use App\Services\Media\ResponsiveMediaKeys;
use App\Services\Media\VariantPolicyVersion;
use DateTimeInterface;
use Illuminate\Database\DatabaseManager;
use stdClass;
use Throwable;

/** Builds B2 ForwardItemEvidence from exact current facts without journal or storage mutation. */
final class ForwardItemEvidenceBuilder
{
    public function __construct(
        private readonly DatabaseManager $database,
        private readonly CandidateManifestReader $candidates,
        private readonly ReconciliationStateValidator $state,
        private readonly ManagedMediaCurrentStateValidator $currentMedia,
        private readonly ExactObjectObserver $objects,
        private readonly ResponsiveMediaInspector $inspector,
        private readonly ResponsiveMediaKeys $keys,
        private readonly StorageObservationCapability $capability,
    ) {}

    public function build(
        string $runId,
        int $itemId,
        DateTimeInterface $observedAt,
        StorageIdentity $identity,
        ReconciliationBackendMode $backendMode,
    ): ForwardItemEvidenceBuildResult {
        $analysis = null;
        try {
            if (! $this->canonicalUuid($runId) || $itemId < 1) {
                return ForwardItemEvidenceBuildResult::refused(ReconciliationBlockReason::InconsistentJournal);
            }
            $this->capability->disk($identity, $backendMode);
            $db = $this->database->connection();
            $run = $db->table('media_backfill_runs')->useWritePdo()->where('run_id', $runId)->first();
            $item = $db->table('media_backfill_items')->useWritePdo()->where('id', $itemId)->first();
            if (! $run instanceof stdClass || ! $item instanceof stdClass
                || ! is_string($run->storage_identity_hash ?? null)
                || ! hash_equals($identity->hash, $run->storage_identity_hash)) {
                return ForwardItemEvidenceBuildResult::refused(ReconciliationBlockReason::InconsistentJournal);
            }
            $planned = $db->table('media_backfill_objects')->useWritePdo()->where('item_id', $itemId)
                ->orderBy('id')->limit(1001)->get()->all();
            if (count($planned) > 1000) {
                return ForwardItemEvidenceBuildResult::refused(ReconciliationBlockReason::InconsistentJournal);
            }

            $analysis = $this->state->analyzeItem($run, $item, $planned);
            if ($analysis->state === ReconciliationItemOperationalState::Inconsistent) {
                return ForwardItemEvidenceBuildResult::refused(
                    ReconciliationBlockReason::InconsistentJournal,
                    $analysis,
                );
            }
            if (($run->reconciliation_event_id ?? null) !== null) {
                return ForwardItemEvidenceBuildResult::refused(
                    $this->state->runClosureProjectionIsValid($run)
                        ? ReconciliationBlockReason::ResolutionConflict
                        : ReconciliationBlockReason::InconsistentJournal,
                    $analysis,
                );
            }
            if (in_array($analysis->state, [ReconciliationItemOperationalState::ResolvedForward,
                ReconciliationItemOperationalState::ResolvedNoEffect], true)) {
                return ForwardItemEvidenceBuildResult::refused(
                    ReconciliationBlockReason::ResolutionConflict,
                    $analysis,
                );
            }
            if (! $analysis->forwardEvidenceRequired) {
                return ForwardItemEvidenceBuildResult::refused(
                    ReconciliationBlockReason::EvidenceMismatch,
                    $analysis,
                );
            }

            [$candidate, $coherent] = $this->candidates->read($item);
            if (! $coherent || $candidate === null || $planned === []) {
                return ForwardItemEvidenceBuildResult::refused(
                    ReconciliationBlockReason::InconsistentJournal,
                    $analysis,
                );
            }
            $descriptors = [(string) $item->manifest_key => null];
            foreach ($candidate->variants as $descriptor) {
                if (array_key_exists($descriptor->key, $descriptors)) {
                    return ForwardItemEvidenceBuildResult::refused(
                        ReconciliationBlockReason::InconsistentJournal,
                        $analysis,
                    );
                }
                $descriptors[$descriptor->key] = $descriptor;
            }
            if (! $this->planIsExact($item, $planned, $descriptors)) {
                return ForwardItemEvidenceBuildResult::refused(
                    ReconciliationBlockReason::InconsistentJournal,
                    $analysis,
                );
            }

            $current = $this->currentMedia->validate($item, $candidate, $identity, $backendMode);
            $this->assertCanonicalTargetUniverse($item, $candidate->variants);

            $observedObjects = [];
            $manifestValidated = false;
            foreach ($planned as $object) {
                $descriptor = $descriptors[(string) $object->object_key];
                $observation = $this->objects->observeExact($object, $identity, $backendMode, $descriptor);
                if ($observation->classification === ObjectObservation::Unreadable) {
                    throw new OperationalEvidenceException(ReconciliationBlockReason::StorageObservationUntrusted);
                }
                if ($observation->classification !== ObjectObservation::ExpectedContentPresent
                    || $observation->observedSha256 === null || $observation->observedSize === null
                    || $observation->observedMimeType === null
                    || ! hash_equals((string) $object->expected_sha256, $observation->observedSha256)
                    || (int) $object->expected_size !== $observation->observedSize
                    || (string) $object->mime_type !== $observation->observedMimeType
                    || ! $observation->descriptorValidated || ! $observation->structureValidated) {
                    throw new OperationalEvidenceException(ReconciliationBlockReason::EvidenceMismatch);
                }
                $manifestValidated = $manifestValidated
                    || ($object->kind ?? null) === ObjectKind::Manifest->value;
                $observedObjects[] = new ForwardObjectEvidence(
                    (int) $object->id,
                    $observation->observedSha256,
                    $observation->observedSize,
                    $observation->observedMimeType,
                    $observation->descriptorValidated,
                    $observation->structureValidated,
                );
            }
            if (! $manifestValidated) {
                throw new OperationalEvidenceException(ReconciliationBlockReason::InconsistentJournal);
            }
            $currentAfterObservation = $this->currentMedia->validate($item, $candidate, $identity, $backendMode);
            if ($currentAfterObservation != $current) {
                throw new OperationalEvidenceException(ReconciliationBlockReason::DomainRevalidationFailed);
            }
            $analysisAfterObservation = $this->state->analyzeItem($run, $item, $planned);
            if ($analysisAfterObservation->state !== ReconciliationItemOperationalState::ForwardCandidate) {
                throw new OperationalEvidenceException(
                    $analysisAfterObservation->state === ReconciliationItemOperationalState::Inconsistent
                        ? ReconciliationBlockReason::InconsistentJournal
                        : ReconciliationBlockReason::ResolutionConflict,
                );
            }
            $analysis = $analysisAfterObservation;
            $this->capability->disk($identity, $backendMode);

            return ForwardItemEvidenceBuildResult::accepted(new ForwardItemEvidence(
                $observedAt,
                $identity->hash,
                $backendMode,
                $current->referenceIdentitySha256,
                $current->masterKeySha256,
                $current->masterSha256,
                (string) $item->candidate_manifest_sha256,
                true,
                $current->liveOwnerDomain,
                $current->liveOwnerEntityId,
                true,
                true,
                $observedObjects,
            ), $analysis);
        } catch (OperationalEvidenceException $error) {
            return ForwardItemEvidenceBuildResult::refused($error->reason, $analysis);
        } catch (ReconciliationException $error) {
            $reason = in_array($error->reason, [ReconciliationError::IdentityMismatch,
                ReconciliationError::UnsupportedStorage], true)
                ? ReconciliationBlockReason::StorageObservationUntrusted
                : ReconciliationBlockReason::InconsistentJournal;

            return ForwardItemEvidenceBuildResult::refused($reason, $analysis);
        } catch (Throwable) {
            return ForwardItemEvidenceBuildResult::refused(
                ReconciliationBlockReason::InconsistentJournal,
                $analysis,
            );
        }
    }

    /** @param list<stdClass> $planned @param array<string, ?ManifestImage> $descriptors */
    private function planIsExact(stdClass $item, array $planned, array $descriptors): bool
    {
        $remaining = $descriptors;
        foreach ($planned as $object) {
            $key = is_string($object->object_key ?? null) ? $object->object_key : '';
            if (! array_key_exists($key, $remaining)
                || CleanupState::tryFrom((string) ($object->cleanup_state ?? '')) === CleanupState::Deleted) {
                return false;
            }
            $descriptor = $remaining[$key];
            unset($remaining[$key]);
            try {
                $kind = ObjectKind::from((string) $object->kind);
                new TargetObject(
                    $key,
                    $kind,
                    (string) $object->expected_sha256,
                    (int) $object->expected_size,
                    (string) $object->mime_type,
                );
            } catch (Throwable) {
                return false;
            }
            if ($descriptor === null) {
                if ($kind !== ObjectKind::Manifest
                    || ! hash_equals((string) $item->candidate_manifest_sha256, (string) $object->expected_sha256)
                    || (int) $object->expected_size !== strlen((string) $item->candidate_manifest_json)
                    || $object->mime_type !== 'application/json') {
                    return false;
                }
            } elseif ($kind !== ObjectKind::Variant
                || (int) $object->expected_size !== $descriptor->size
                || $object->mime_type !== $descriptor->mimeType) {
                return false;
            }
        }

        return $remaining === [];
    }

    /** @param list<ManifestImage> $candidateVariants */
    private function assertCanonicalTargetUniverse(stdClass $item, array $candidateVariants): void
    {
        $expected = [];
        foreach ($candidateVariants as $variant) {
            $expected[$variant->key] = true;
        }
        $universe = [];
        foreach (VariantPolicyVersion::V1->widths(ManagedMediaDomain::from((string) $item->domain)->profile()) as $width) {
            foreach ([ImageFormat::Png, ImageFormat::Webp] as $format) {
                $key = $this->keys->variant(
                    (string) $item->master_key,
                    ManagedMediaDomain::from((string) $item->domain)->profile(),
                    VariantPolicyVersion::V1,
                    $width,
                    $format,
                );
                $universe[$key] = true;
            }
        }
        try {
            $observed = $this->inspector->inspectTargets(
                (string) $item->master_key,
                ManagedMediaDomain::from((string) $item->domain)->profile(),
            );
        } catch (Throwable) {
            throw new OperationalEvidenceException(ReconciliationBlockReason::StorageObservationUntrusted);
        }
        if (array_keys($observed) !== array_keys($universe)) {
            throw new OperationalEvidenceException(ReconciliationBlockReason::StorageObservationUntrusted);
        }
        foreach ($observed as $key => $inspection) {
            if (isset($expected[$key])) {
                if ($inspection->state === ObjectInspectionState::InspectionFailed) {
                    throw new OperationalEvidenceException(ReconciliationBlockReason::StorageObservationUntrusted);
                }
                if ($inspection->state !== ObjectInspectionState::Present) {
                    throw new OperationalEvidenceException(ReconciliationBlockReason::EvidenceMismatch);
                }
            } elseif ($inspection->state === ObjectInspectionState::InspectionFailed) {
                throw new OperationalEvidenceException(ReconciliationBlockReason::StorageObservationUntrusted);
            } elseif ($inspection->state !== ObjectInspectionState::Missing) {
                throw new OperationalEvidenceException(ReconciliationBlockReason::EvidenceMismatch);
            }
        }
    }

    private function canonicalUuid(string $value): bool
    {
        return preg_match('/\A[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}\z/D', $value) === 1;
    }
}
