<?php

namespace Tests\Concerns;

use App\Services\Media\Backfill\ManagedMediaDomain;
use App\Services\Media\Backfill\ManagedMediaReference;
use App\Services\Media\Backfill\PreflightClassification;
use App\Services\Media\Backfill\PreflightResult;
use App\Services\Media\Backfill\Reconciliation\ForwardItemEvidence;
use App\Services\Media\Backfill\Reconciliation\ForwardObjectEvidence;
use App\Services\Media\Backfill\Reconciliation\ReconciliationBackendMode;
use App\Services\Media\Backfill\Reconciliation\ReconciliationContext;
use App\Services\Media\Backfill\Reconciliation\ReconciliationJournal;
use App\Services\Media\Backfill\Safety\AdvisoryLockHandle;
use App\Services\Media\Backfill\Safety\ApplyJournal;
use App\Services\Media\Backfill\Safety\ApplyRunSelection;
use App\Services\Media\Backfill\Safety\BackfillSafetyException;
use App\Services\Media\Backfill\Safety\LockAcquireState;
use App\Services\Media\Backfill\Safety\MariaDbBackfillLock;
use App\Services\Media\Backfill\Safety\ObjectKind;
use App\Services\Media\Backfill\Safety\SafetyError;
use App\Services\Media\Backfill\Safety\StorageIdentity;
use App\Services\Media\Backfill\Safety\TargetObject;
use App\Services\Media\ExistingMasterPreparer;
use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\Unit\Media\ResponsiveImageFixtures;

trait BackfillSafetyFixtures
{
    use ResponsiveImageFixtures;

    private string $safetyRoot;

    private ?AdvisoryLockHandle $backfillLock = null;

    private const SAFETY_UUID = '550e8400-e29b-41d4-a716-446655440000';

    private function setupSafetyStorage(): void
    {
        $this->safetyRoot = sys_get_temp_dir().'/galotxas-backfill-test-'.Str::uuid();
        mkdir($this->safetyRoot, 0700);
        config()->set('media.disk', 'media_local');
        config()->set('filesystems.disks.media_local.root', $this->safetyRoot);
        Storage::forgetDisk('media_local');
    }

    private function cleanupSafetyStorage(): void
    {
        if (isset($this->safetyRoot)) {
            File::deleteDirectory($this->safetyRoot);
        }
    }

    private function identity(): StorageIdentity
    {
        return StorageIdentity::current(app('db'));
    }

    private function variant(): array
    {
        $bytes = $this->fixtureBytes(320, 160, 'webp');

        return [new TargetObject('variants/v1/news/'.self::SAFETY_UUID.'/w320.webp', ObjectKind::Variant,
            hash('sha256', $bytes), strlen($bytes), 'image/webp'), $bytes];
    }

    private function preflight(int $entityId = 123): PreflightResult
    {
        $domain = ManagedMediaDomain::News;
        $key = 'news/'.self::SAFETY_UUID.'.jpg';
        $prepared = app(ExistingMasterPreparer::class)->prepare($key, $this->fixtureBytes(400, 200, 'jpeg'), $domain->profile(), $domain->policy());

        return new PreflightResult(new ManagedMediaReference($domain, $entityId, $key), PreflightClassification::LegacyBackfillable, prepared: $prepared);
    }

    private function plannedObject(): array
    {
        $journal = app(ApplyJournal::class);
        $run = $journal->createApplyRun($this->identity(), $this->applySelection());
        $preflight = $this->preflight();
        $item = $journal->snapshot($run, $preflight);
        $bytes = $preflight->prepared->variants[0]->bytes;
        $descriptor = $preflight->prepared->manifest->variants[0];
        $target = new TargetObject($descriptor->key, ObjectKind::Variant, hash('sha256', $bytes), strlen($bytes), $descriptor->mimeType);
        $object = $journal->planObject($item, $target);
        $journal->markRevalidated($item);

        return [$journal, $run, $item, $object, $target, $bytes];
    }

    /** @return array{ApplyJournal, string, int, array{variant: int, manifest: int}, array<int, array{key: string, bytes: string}>} */
    private function candidatePlan(): array
    {
        $journal = app(ApplyJournal::class);
        $runId = $journal->createApplyRun($this->identity(), $this->applySelection());
        [$itemId, $objects, $bytes] = $this->candidateItem($journal, $runId, 123);

        return [$journal, $runId, $itemId, $objects, $bytes];
    }

    /** @return array{int, array{variant: int, manifest: int}, array<int, array{key: string, bytes: string}>} */
    private function candidateItem(ApplyJournal $journal, string $runId, int $entityId): array
    {
        $preflight = $this->preflight($entityId);
        $itemId = $journal->snapshot($runId, $preflight);
        $objects = [];
        $bytes = [];
        foreach ($preflight->prepared->manifest->variants as $index => $descriptor) {
            $image = $preflight->prepared->variants[$index];
            $target = new TargetObject(
                $descriptor->key,
                ObjectKind::Variant,
                hash('sha256', $image->bytes),
                $image->size,
                $image->mimeType,
            );
            $objectId = $journal->planObject($itemId, $target);
            $objects['variant'] = $objectId;
            $bytes[$objectId] = ['key' => $target->key, 'bytes' => $image->bytes];
        }
        $manifestBytes = $preflight->prepared->manifest->toJson();
        $manifestTarget = new TargetObject(
            $journal->item($itemId)->manifest_key,
            ObjectKind::Manifest,
            hash('sha256', $manifestBytes),
            strlen($manifestBytes),
            'application/json',
        );
        $manifestId = $journal->planObject($itemId, $manifestTarget);
        $objects['manifest'] = $manifestId;
        $bytes[$manifestId] = ['key' => $manifestTarget->key, 'bytes' => $manifestBytes];
        $journal->markRevalidated($itemId);

        return [$itemId, $objects, $bytes];
    }

    private function excludedItem(int $entityId): PreflightResult
    {
        return new PreflightResult(
            new ManagedMediaReference(ManagedMediaDomain::News, $entityId, null),
            PreflightClassification::ExcludedNull,
        );
    }

    /** One exclusive session lock per test: the advisory lock family is deliberately exclusive. */
    private function backfillLock(): AdvisoryLockHandle
    {
        if ($this->backfillLock === null) {
            $acquisition = app(MariaDbBackfillLock::class)->acquire($this->identity());
            $this->assertSame(LockAcquireState::Acquired, $acquisition->state);
            $this->backfillLock = $acquisition->handle;
        }

        return $this->backfillLock;
    }

    private function releaseBackfillLock(): void
    {
        $this->backfillLock?->close();
        $this->backfillLock = null;
    }

    private function reconciliationContext(
        int $attempt = 90,
        bool $withRevision = true,
        ?ReconciliationBackendMode $mode = null,
    ): ReconciliationContext {
        return new ReconciliationContext(
            $this->reconciliationUuid($attempt),
            $this->backfillLock(),
            $this->identity(),
            $mode ?? ReconciliationBackendMode::Local,
            $withRevision ? str_repeat('a', 40) : null,
        );
    }

    private function reconciliationUuid(int $number): string
    {
        return sprintf('550e8400-e29b-41d4-a716-44665544%04d', $number);
    }

    private function reconciliationMoment(string $time = '10:00:00'): DateTimeImmutable
    {
        return new DateTimeImmutable('2026-09-12 '.$time, new DateTimeZone('UTC'));
    }

    /** @return list<int> */
    private function objectIds(int $itemId): array
    {
        return DB::table('media_backfill_objects')->where('item_id', $itemId)->orderBy('id')
            ->pluck('id')->map(static fn ($id): int => (int) $id)->all();
    }

    /** @return list<ForwardObjectEvidence> */
    private function observedObjects(int $itemId, array $overrides = []): array
    {
        $objects = [];
        foreach (DB::table('media_backfill_objects')->where('item_id', $itemId)->orderBy('id')->get() as $index => $row) {
            $values = [
                'objectId' => (int) $row->id,
                'observedSha256' => $row->expected_sha256,
                'observedSize' => (int) $row->expected_size,
                'observedMimeType' => $row->mime_type,
                'descriptorValidated' => true,
                'structureValidated' => true,
            ];
            $objects[] = new ForwardObjectEvidence(...($index === 0 ? array_merge($values, $overrides) : $values));
        }

        return $objects;
    }

    private function forwardEvidenceFor(int $itemId, array $overrides = [], array $objectOverrides = []): ForwardItemEvidence
    {
        $item = DB::table('media_backfill_items')->where('id', $itemId)->first();
        $master = (string) $item->master_key;
        $arguments = [
            'observedAt' => $this->reconciliationMoment(),
            'storageIdentityHash' => $this->identity()->hash,
            'backendMode' => ReconciliationBackendMode::Local,
            'referenceIdentitySha256' => hash('sha256', substr($master, 0, (int) strrpos($master, '.'))),
            'masterKeySha256' => (string) $item->master_key_hash,
            'masterSha256' => (string) $item->source_sha256,
            'candidateManifestSha256' => (string) $item->candidate_manifest_sha256,
            'uniqueLiveOwnerRevalidated' => true,
            'liveOwnerDomain' => (string) $item->domain,
            'liveOwnerEntityId' => (int) $item->entity_id,
            'manifestStructureValidated' => true,
            'noUnexpectedCanonicalTarget' => true,
            'objects' => $this->observedObjects($itemId, $objectOverrides),
        ];

        return new ForwardItemEvidence(...array_merge($arguments, $overrides));
    }

    /** Supported durable state: a started attempt plus one item-atomic forward acceptance. */
    private function acceptForward(string $runId, int $itemId, int $attempt, int $startEvent, int $forwardEvent): string
    {
        $context = $this->reconciliationContext($attempt);
        $journal = app(ReconciliationJournal::class);
        $journal->beginRunReconciliation($runId, $this->reconciliationUuid($startEvent),
            $this->reconciliationMoment(), $context);
        $journal->recordForwardItemResolution($runId, $itemId, $this->reconciliationUuid($forwardEvent),
            $this->forwardEvidenceFor($itemId), $context);

        return $this->reconciliationUuid($forwardEvent);
    }

    private function applySelection(
        ManagedMediaDomain $domain = ManagedMediaDomain::News,
        int $afterId = 0,
        int $limit = 1000,
        int $upperBound = 1000,
    ): ApplyRunSelection {
        return new ApplyRunSelection($domain, $afterId, $limit, $upperBound);
    }

    private function assertSafetyError(SafetyError $reason, callable $callback): void
    {
        try {
            $callback();
            $this->fail('Expected a typed safety failure.');
        } catch (BackfillSafetyException $error) {
            $this->assertSame($reason, $error->reason);
            $this->assertSame($reason->value, $error->getMessage());
            $this->assertNull($error->getPrevious());
        }
    }
}
