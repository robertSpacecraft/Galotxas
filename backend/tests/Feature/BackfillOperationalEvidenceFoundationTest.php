<?php

namespace Tests\Feature;

use App\Models\NewsArticle;
use App\Services\Media\Backfill\ManagedMediaDomain;
use App\Services\Media\Backfill\ManagedMediaReferenceRegistry;
use App\Services\Media\Backfill\ObjectInspection;
use App\Services\Media\Backfill\ObjectInspectionState;
use App\Services\Media\Backfill\PreflightClassification;
use App\Services\Media\Backfill\PreflightResult;
use App\Services\Media\Backfill\Reconciliation\ExactObjectObservation;
use App\Services\Media\Backfill\Reconciliation\ExactObjectObserver;
use App\Services\Media\Backfill\Reconciliation\ForwardItemEvidenceBuilder;
use App\Services\Media\Backfill\Reconciliation\ForwardItemEvidenceBuildResult;
use App\Services\Media\Backfill\Reconciliation\ManagedMediaCurrentStateValidator;
use App\Services\Media\Backfill\Reconciliation\OperationalEvidenceException;
use App\Services\Media\Backfill\Reconciliation\ReconciliationBackendMode;
use App\Services\Media\Backfill\Reconciliation\ReconciliationBlockReason;
use App\Services\Media\Backfill\Reconciliation\ReconciliationError;
use App\Services\Media\Backfill\Reconciliation\ReconciliationException;
use App\Services\Media\Backfill\Reconciliation\ReconciliationItemOperationalAnalysis;
use App\Services\Media\Backfill\Reconciliation\ReconciliationItemOperationalState;
use App\Services\Media\Backfill\Reconciliation\ReconciliationJournal;
use App\Services\Media\Backfill\Reconciliation\ReconciliationStateValidator;
use App\Services\Media\Backfill\Reconciliation\StorageObservationCapability;
use App\Services\Media\Backfill\ResponsiveBackfillPreflight;
use App\Services\Media\Backfill\ResponsiveMediaInspector;
use App\Services\Media\Backfill\Safety\ApplyJournal;
use App\Services\Media\Backfill\Safety\ApplyResult;
use App\Services\Media\Backfill\Safety\ApplyRunSelection;
use App\Services\Media\Backfill\Safety\CleanupState;
use App\Services\Media\Backfill\Safety\CreateReceipt;
use App\Services\Media\Backfill\Safety\CreateState;
use App\Services\Media\Backfill\Safety\ObjectKind;
use App\Services\Media\Backfill\Safety\StorageIdentity;
use App\Services\Media\Backfill\Safety\TargetObject;
use App\Services\Media\ExistingMasterPreparer;
use DateTimeImmutable;
use DateTimeZone;
use GuzzleHttp\Promise\Create;
use GuzzleHttp\Psr7\Response;
use Illuminate\Filesystem\AwsS3V3Adapter;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Filesystem\FilesystemManager;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Mockery;
use Psr\Http\Message\RequestInterface;
use Tests\Concerns\BackfillSafetyFixtures;
use Tests\TestCase;

class BackfillOperationalEvidenceFoundationTest extends TestCase
{
    use BackfillSafetyFixtures;
    use DatabaseTruncation;

    private const UUID = '750e8400-e29b-41d4-a716-446655440000';

    protected function setUp(): void
    {
        parent::setUp();
        $this->assertSame('galotxas_testing', DB::connection()->getDatabaseName());
        $this->assertSame('test-db', DB::connection()->getConfig('host'));
        $this->setupSafetyStorage();
    }

    protected function tearDown(): void
    {
        try {
            $this->releaseBackfillLock();
            $this->truncateTablesForAllConnections();
            $this->cleanupSafetyStorage();
        } finally {
            parent::tearDown();
        }
    }

    public function test_runtime_capability_accepts_exact_local_adapter_and_rejects_stale_or_wrong_runtime(): void
    {
        $identity = $this->identity();
        $this->assertSame(
            Storage::disk('media_local'),
            app(StorageObservationCapability::class)->disk($identity, ReconciliationBackendMode::Local),
        );

        $otherRoot = $this->safetyRoot.'/other';
        mkdir($otherRoot, 0700);
        config()->set('filesystems.disks.media_local.root', $otherRoot);
        $otherIdentity = StorageIdentity::current(app('db'));
        $this->assertReconciliationError(
            ReconciliationError::UnsupportedStorage,
            fn () => app(StorageObservationCapability::class)->disk(
                $otherIdentity,
                ReconciliationBackendMode::Local,
            ),
        );

        Storage::forgetDisk('media_local');
        $runtime = config('filesystems.disks.media_local');
        $disk = Mockery::mock(FilesystemAdapter::class);
        $disk->shouldReceive('getConfig')->andReturn($runtime);
        $disk->shouldReceive('getAdapter')->andReturn(new \stdClass);
        $manager = Mockery::mock(FilesystemManager::class);
        $manager->shouldReceive('disk')->with('media_local')->andReturn($disk);
        $capability = new StorageObservationCapability($manager, app('db'));
        $this->assertReconciliationError(
            ReconciliationError::UnsupportedStorage,
            fn () => $capability->disk(StorageIdentity::current(app('db')), ReconciliationBackendMode::Local),
        );

        config()->set('filesystems.disks.media_local.root', $this->safetyRoot);
        Storage::forgetDisk('media_local');
        $this->assertReconciliationError(
            ReconciliationError::IdentityMismatch,
            fn () => app(StorageObservationCapability::class)->disk(
                $otherIdentity,
                ReconciliationBackendMode::Local,
            ),
        );
    }

    public function test_runtime_capability_accepts_actual_s3_adapter_without_a_probe_request(): void
    {
        $requests = [];
        $this->installS3([], $requests);

        $disk = app(StorageObservationCapability::class)->disk(
            $this->identity(),
            ReconciliationBackendMode::S3,
        );

        $this->assertInstanceOf(AwsS3V3Adapter::class, $disk);
        $this->assertSame([], $requests);

        Storage::set('media_s3', Storage::disk('media_local'));
        $this->assertReconciliationError(
            ReconciliationError::UnsupportedStorage,
            fn () => app(StorageObservationCapability::class)->disk(
                $this->identity(),
                ReconciliationBackendMode::S3,
            ),
        );
        $this->assertSame([], $requests);
    }

    public function test_current_media_validator_proves_unique_owner_reference_metadata_and_master_without_mutation(): void
    {
        $candidate = $this->candidate();
        $item = $candidate['journal']->item($candidate['item']);
        $before = $candidate['row']->fresh()->getAttributes();

        $state = app(ManagedMediaCurrentStateValidator::class)->validate(
            $item,
            $candidate['prepared']->manifest,
            $this->identity(),
            ReconciliationBackendMode::Local,
        );

        $this->assertSame(hash('sha256', substr($candidate['master_key'], 0, -5)), $state->referenceIdentitySha256);
        $this->assertSame($item->master_key_hash, $state->masterKeySha256);
        $this->assertSame($item->source_sha256, $state->masterSha256);
        $this->assertSame(ManagedMediaDomain::News->value, $state->liveOwnerDomain);
        $this->assertSame($candidate['row']->id, $state->liveOwnerEntityId);
        $this->assertSame($before, $candidate['row']->fresh()->getAttributes());
    }

    public function test_current_media_validator_fails_closed_on_owner_reference_metadata_or_master_drift(): void
    {
        foreach (['missing', 'multiple_owner', 'reference', 'metadata', 'master'] as $case) {
            $this->truncateTablesForAllConnections();
            File::cleanDirectory($this->safetyRoot);
            $candidate = $this->candidate();
            if ($case === 'missing') {
                $candidate['row']->forceDelete();
            } elseif ($case === 'multiple_owner') {
                NewsArticle::factory()->create([
                    'image_key' => substr($candidate['master_key'], 0, -5).'.jpg',
                    'image_width' => 400,
                    'image_height' => 200,
                ]);
            } elseif ($case === 'reference') {
                $candidate['row']->update(['image_key' => 'news/850e8400-e29b-41d4-a716-446655440000.webp']);
            } elseif ($case === 'metadata') {
                $candidate['row']->update(['image_width' => 399]);
            } else {
                Storage::disk('media_local')->put($candidate['master_key'], $candidate['master_bytes'].'x');
            }

            try {
                app(ManagedMediaCurrentStateValidator::class)->validate(
                    $candidate['journal']->item($candidate['item']),
                    $candidate['prepared']->manifest,
                    $this->identity(),
                    ReconciliationBackendMode::Local,
                );
                $this->fail('Expected current-state validation to fail for '.$case);
            } catch (OperationalEvidenceException $error) {
                $this->assertContains($error->reason, [
                    ReconciliationBlockReason::DomainRevalidationFailed,
                    ReconciliationBlockReason::EvidenceMismatch,
                ], $case);
                $this->assertNull($error->getPrevious());
            }
        }
    }

    public function test_db_only_analysis_distinguishes_no_effect_forward_and_cleanup_attention(): void
    {
        $noEffect = $this->candidate(ambiguous: false);
        $analysis = $this->analysis($noEffect);
        $this->assertSame(ReconciliationItemOperationalState::NoEffectCandidate, $analysis->state);
        $this->assertTrue($analysis->noEffectEligible);
        $this->assertFalse($analysis->forwardEvidenceRequired);
        $this->assertSame([], $analysis->ambiguousWriteObjectIds);

        $this->truncateTablesForAllConnections();
        File::cleanDirectory($this->safetyRoot);
        $forward = $this->candidate();
        $analysis = $this->analysis($forward);
        $this->assertSame(ReconciliationItemOperationalState::ForwardCandidate, $analysis->state);
        $this->assertFalse($analysis->noEffectEligible);
        $this->assertTrue($analysis->forwardEvidenceRequired);
        $this->assertSame([$forward['object_ids'][0]], $analysis->ambiguousWriteObjectIds);

        $forward['journal']->recordReceipt($forward['object_ids'][0], new CreateReceipt(CreateState::Created));
        $forward['journal']->updateCleanup($forward['object_ids'][0], CleanupState::Pending);
        $analysis = $this->analysis($forward);
        $this->assertSame(ReconciliationItemOperationalState::ForwardCandidate, $analysis->state);
        $this->assertTrue($analysis->hasCleanupAttention());
        $this->assertSame([$forward['object_ids'][0]], $analysis->cleanupBlockerObjectIds);
        $this->assertFalse($analysis->noEffectEligible);
    }

    public function test_db_only_no_effect_eligibility_ignores_current_domain_master_and_storage_state(): void
    {
        $candidate = $this->candidate(ambiguous: false);
        $candidate['row']->forceDelete();
        File::cleanDirectory($this->safetyRoot);

        $analysis = $this->analysis($candidate);

        $this->assertSame(ReconciliationItemOperationalState::NoEffectCandidate, $analysis->state);
        $this->assertTrue($analysis->noEffectEligible);
        $this->assertFalse($analysis->forwardEvidenceRequired);
    }

    public function test_db_only_analysis_recognizes_ordinary_terminal_history_and_duplicate_input_as_inconsistent(): void
    {
        $candidate = $this->candidate(ambiguous: false);
        $candidate['journal']->finishItem($candidate['item'], ApplyResult::FailedNoWrites);
        $this->assertSame(ReconciliationItemOperationalState::OrdinaryNonBlocking, $this->analysis($candidate)->state);

        $objects = DB::table('media_backfill_objects')->where('item_id', $candidate['item'])->orderBy('id')->get()->all();
        $duplicate = clone $objects[0];
        $duplicate->id = (int) end($objects)->id + 1;
        $objects[] = $duplicate;
        $analysis = app(ReconciliationStateValidator::class)->analyzeItem(
            $candidate['journal']->run($candidate['run']),
            $candidate['journal']->item($candidate['item']),
            $objects,
        );
        $this->assertSame(ReconciliationItemOperationalState::Inconsistent, $analysis->state);
    }

    public function test_db_only_analysis_rejects_no_effect_after_any_write_receipt_or_cleanup_activity(): void
    {
        foreach (['intent', 'unknown', 'created', 'cleanup'] as $case) {
            $this->truncateTablesForAllConnections();
            File::cleanDirectory($this->safetyRoot);
            $candidate = $this->candidate(ambiguous: false);
            $journal = $candidate['journal'];
            $object = $candidate['object_ids'][0];
            $journal->markRevalidated($candidate['item']);
            if ($case === 'intent') {
                $journal->commitIntent($object);
            } elseif ($case === 'unknown') {
                $journal->commitIntent($object);
                $journal->recordReceipt($object, new CreateReceipt(CreateState::Unknown));
            } else {
                $journal->commitIntent($object);
                $journal->recordReceipt($object, new CreateReceipt(CreateState::Created, 'opaque', 'opaque'));
                if ($case === 'cleanup') {
                    $journal->updateCleanup($object, CleanupState::Pending);
                }
            }

            $analysis = $this->analysis($candidate);
            $this->assertFalse($analysis->noEffectEligible, $case);
            $this->assertNotSame(ReconciliationItemOperationalState::NoEffectCandidate, $analysis->state, $case);
        }
    }

    public function test_db_only_analysis_recognizes_exact_resolutions_and_fails_closed_on_partial_projection(): void
    {
        $candidate = $this->candidate();
        $this->acceptForward($candidate['run'], $candidate['item'], 90, 1, 2);
        $this->assertSame(ReconciliationItemOperationalState::ResolvedForward, $this->analysis($candidate)->state);

        DB::table('media_backfill_objects')->where('id', $candidate['object_ids'][0])
            ->update(['reconciliation_event_id' => null]);
        $this->assertSame(ReconciliationItemOperationalState::Inconsistent, $this->analysis($candidate)->state);

        $this->truncateTablesForAllConnections();
        File::cleanDirectory($this->safetyRoot);
        $noEffect = $this->candidate(ambiguous: false);
        $context = $this->reconciliationContext(91);
        $journal = app(ReconciliationJournal::class);
        $journal->beginRunReconciliation(
            $noEffect['run'],
            $this->reconciliationUuid(3),
            $this->reconciliationMoment(),
            $context,
        );
        $journal->recordNoEffectItemResolution(
            $noEffect['run'],
            $noEffect['item'],
            $this->reconciliationUuid(4),
            $this->reconciliationMoment(),
            $context,
        );
        $this->assertSame(ReconciliationItemOperationalState::ResolvedNoEffect, $this->analysis($noEffect)->state);
    }

    public function test_forward_builder_constructs_existing_evidence_from_actual_local_facts_without_mutation(): void
    {
        $candidate = $this->candidate();
        $beforeJournal = $this->journalSnapshot();
        $beforeStorage = $this->storageSnapshot();

        $result = $this->build($candidate);

        $this->assertTrue($result->isAccepted());
        $this->assertNull($result->refusal);
        $this->assertSame(ReconciliationItemOperationalState::ForwardCandidate, $result->analysis->state);
        $this->assertSame($this->identity()->hash, $result->evidence->storageIdentityHash);
        $this->assertSame(ReconciliationBackendMode::Local, $result->evidence->backendMode);
        $this->assertTrue($result->evidence->uniqueLiveOwnerRevalidated);
        $this->assertTrue($result->evidence->manifestStructureValidated);
        $this->assertTrue($result->evidence->noUnexpectedCanonicalTarget);
        $this->assertSame($candidate['object_ids'], $result->evidence->objectIds());
        foreach ($result->evidence->objects as $object) {
            $row = DB::table('media_backfill_objects')->where('id', $object->objectId)->first();
            $this->assertSame($row->expected_sha256, $object->observedSha256);
            $this->assertSame((int) $row->expected_size, $object->observedSize);
            $this->assertSame($row->mime_type, $object->observedMimeType);
            $this->assertTrue($object->descriptorValidated);
            $this->assertTrue($object->structureValidated);
        }
        $this->assertSame($beforeJournal, $this->journalSnapshot());
        $this->assertSame($beforeStorage, $this->storageSnapshot());
        $this->assertSame(0, DB::table('media_backfill_reconciliation_events')->count());
    }

    public function test_forward_builder_uses_durable_variant_hash_and_manifest_hash_agreement(): void
    {
        $candidate = $this->candidate();
        $variantId = $candidate['object_ids'][0];
        DB::table('media_backfill_objects')->where('id', $variantId)
            ->update(['expected_sha256' => str_repeat('a', 64)]);
        $result = $this->build($candidate);
        $this->assertFalse($result->isAccepted());
        $this->assertSame(ReconciliationBlockReason::EvidenceMismatch, $result->refusal);

        DB::table('media_backfill_objects')->where('id', $variantId)
            ->update(['expected_sha256' => hash('sha256', $candidate['object_bytes'][$variantId])]);
        $manifestId = $candidate['object_ids'][count($candidate['object_ids']) - 1];
        DB::table('media_backfill_objects')->where('id', $manifestId)
            ->update(['expected_sha256' => str_repeat('b', 64)]);
        $result = $this->build($candidate);
        $this->assertFalse($result->isAccepted());
        $this->assertSame(ReconciliationBlockReason::InconsistentJournal, $result->refusal);
        $this->assertFalse(property_exists($candidate['prepared']->manifest->variants[0], 'sha256'));
    }

    public function test_forward_builder_rejects_current_manifest_bytes_and_master_descriptor_drift(): void
    {
        $candidate = $this->candidate();
        $manifestId = $candidate['object_ids'][count($candidate['object_ids']) - 1];
        $manifest = $candidate['object_bytes'][$manifestId];
        Storage::disk('media_local')->put(
            $candidate['object_keys'][$manifestId],
            '['.substr($manifest, 1),
        );
        $result = $this->build($candidate);
        $this->assertFalse($result->isAccepted());
        $this->assertSame(ReconciliationBlockReason::EvidenceMismatch, $result->refusal);

        Storage::disk('media_local')->put($candidate['object_keys'][$manifestId], $manifest);
        $wrongDescriptorBytes = $this->fixtureBytes(400, 199, 'webp');
        Storage::disk('media_local')->put($candidate['master_key'], $wrongDescriptorBytes);
        DB::table('media_backfill_items')->where('id', $candidate['item'])
            ->update(['source_sha256' => hash('sha256', $wrongDescriptorBytes)]);
        $result = $this->build($candidate);
        $this->assertFalse($result->isAccepted());
        $this->assertSame(ReconciliationBlockReason::EvidenceMismatch, $result->refusal);
    }

    public function test_forward_builder_blocks_absent_different_and_unexpected_targets(): void
    {
        foreach (['absent', 'different', 'unexpected'] as $case) {
            $this->truncateTablesForAllConnections();
            File::cleanDirectory($this->safetyRoot);
            $candidate = $this->candidate();
            $target = $candidate['object_keys'][$candidate['object_ids'][0]];
            if ($case === 'absent') {
                Storage::disk('media_local')->delete($target);
            } elseif ($case === 'different') {
                Storage::disk('media_local')->put($target, str_repeat('x', strlen($candidate['object_bytes'][$candidate['object_ids'][0]])));
            } else {
                Storage::disk('media_local')->put(
                    'variants/v1/news/'.self::UUID.'/w320.png',
                    $this->fixtureBytes(320, 160, 'png'),
                );
            }

            $result = $this->build($candidate);
            $this->assertFalse($result->isAccepted(), $case);
            $this->assertSame(ReconciliationBlockReason::EvidenceMismatch, $result->refusal, $case);
            $this->assertSame(0, DB::table('media_backfill_reconciliation_events')->count(), $case);
        }
    }

    public function test_forward_builder_blocks_unreadable_expected_or_unexpected_targets_and_invalid_structure(): void
    {
        $candidate = $this->candidate();
        $observer = Mockery::mock(ExactObjectObserver::class);
        $observer->shouldReceive('observeExact')->once()->andReturn(ExactObjectObservation::unreadable());
        $this->instance(ExactObjectObserver::class, $observer);
        $result = $this->build($candidate);
        $this->assertFalse($result->isAccepted());
        $this->assertSame(ReconciliationBlockReason::StorageObservationUntrusted, $result->refusal);

        $this->truncateTablesForAllConnections();
        File::cleanDirectory($this->safetyRoot);
        $candidate = $this->candidate();
        $realInspector = app(ResponsiveMediaInspector::class);
        $expected = array_column($candidate['prepared']->manifest->variants, 'key');
        $inspector = Mockery::mock(ResponsiveMediaInspector::class);
        $inspector->shouldReceive('inspectMaster')->andReturnUsing(
            fn (...$arguments) => $realInspector->inspectMaster(...$arguments),
        );
        $inspector->shouldReceive('matchesDescriptor')->andReturnUsing(
            fn (...$arguments) => $realInspector->matchesDescriptor(...$arguments),
        );
        $inspector->shouldReceive('inspectTargets')->once()->andReturnUsing(
            function (...$arguments) use ($realInspector, $expected) {
                $targets = $realInspector->inspectTargets(...$arguments);
                foreach ($targets as $key => $target) {
                    if (! in_array($key, $expected, true)) {
                        $targets[$key] = new ObjectInspection(ObjectInspectionState::InspectionFailed);
                        break;
                    }
                }

                return $targets;
            },
        );
        $this->instance(ResponsiveMediaInspector::class, $inspector);
        $result = $this->build($candidate);
        $this->assertFalse($result->isAccepted());
        $this->assertSame(ReconciliationBlockReason::StorageObservationUntrusted, $result->refusal);

        $this->truncateTablesForAllConnections();
        File::cleanDirectory($this->safetyRoot);
        $this->app->forgetInstance(ExactObjectObserver::class);
        $this->app->forgetInstance(ResponsiveMediaInspector::class);
        $candidate = $this->candidate();
        $object = $candidate['object_ids'][0];
        $invalid = str_repeat('x', strlen($candidate['object_bytes'][$object]));
        DB::table('media_backfill_objects')->where('id', $object)
            ->update(['expected_sha256' => hash('sha256', $invalid)]);
        Storage::disk('media_local')->put($candidate['object_keys'][$object], $invalid);
        $result = $this->build($candidate);
        $this->assertFalse($result->isAccepted());
        $this->assertSame(ReconciliationBlockReason::EvidenceMismatch, $result->refusal);
    }

    public function test_forward_builder_blocks_domain_candidate_plan_and_deleted_cleanup_drift(): void
    {
        foreach (['owner', 'candidate', 'missing_plan', 'extra_plan', 'deleted_cleanup'] as $case) {
            $this->truncateTablesForAllConnections();
            File::cleanDirectory($this->safetyRoot);
            $candidate = $this->candidate();
            if ($case === 'owner') {
                $candidate['row']->update(['image_width' => 399]);
            } elseif ($case === 'candidate') {
                DB::table('media_backfill_items')->where('id', $candidate['item'])
                    ->update(['candidate_manifest_sha256' => str_repeat('c', 64)]);
            } elseif ($case === 'missing_plan') {
                DB::table('media_backfill_objects')->where('id', $candidate['object_ids'][0])->delete();
            } elseif ($case === 'extra_plan') {
                $row = (array) DB::table('media_backfill_objects')->where('id', $candidate['object_ids'][0])->first();
                unset($row['id']);
                $row['object_key'] = 'variants/v1/news/'.self::UUID.'/w320.png';
                $row['mime_type'] = 'image/png';
                $row['expected_sha256'] = hash('sha256', $this->fixtureBytes(320, 160, 'png'));
                $row['expected_size'] = strlen($this->fixtureBytes(320, 160, 'png'));
                DB::table('media_backfill_objects')->insert($row);
            } else {
                $object = $candidate['object_ids'][0];
                $candidate['journal']->recordReceipt($object, new CreateReceipt(CreateState::Created));
                $candidate['journal']->updateCleanup($object, CleanupState::Pending);
                $candidate['journal']->updateCleanup($object, CleanupState::Deleted);
            }

            $result = $this->build($candidate);
            $this->assertFalse($result->isAccepted(), $case);
            $this->assertContains($result->refusal, [
                ReconciliationBlockReason::DomainRevalidationFailed,
                ReconciliationBlockReason::InconsistentJournal,
                ReconciliationBlockReason::EvidenceMismatch,
            ], $case);
        }
    }

    public function test_forward_evidence_can_be_built_with_cleanup_attention_but_cleanup_remains_visible(): void
    {
        foreach ([CleanupState::Pending, CleanupState::Failed, CleanupState::Unknown] as $cleanup) {
            $this->truncateTablesForAllConnections();
            File::cleanDirectory($this->safetyRoot);
            $candidate = $this->candidate();
            $object = $candidate['object_ids'][0];
            $candidate['journal']->recordReceipt($object, new CreateReceipt(CreateState::Created));
            $candidate['journal']->updateCleanup($object, CleanupState::Pending);
            if ($cleanup !== CleanupState::Pending) {
                $candidate['journal']->updateCleanup($object, $cleanup);
            }

            $result = $this->build($candidate);

            $this->assertTrue($result->isAccepted(), $cleanup->value);
            $this->assertTrue($result->analysis->hasCleanupAttention(), $cleanup->value);
            $this->assertSame([$object], $result->analysis->cleanupBlockerObjectIds, $cleanup->value);
            $this->assertSame($cleanup->value,
                DB::table('media_backfill_objects')->where('id', $object)->value('cleanup_state'));
        }
    }

    public function test_forward_builder_supports_exact_s3_get_reads_without_write_delete_copy_or_list(): void
    {
        $masterKey = 'news/'.self::UUID.'.webp';
        $masterBytes = $this->fixtureBytes(400, 200, 'webp');
        $prepared = app(ExistingMasterPreparer::class)->prepare(
            $masterKey,
            $masterBytes,
            ManagedMediaDomain::News->profile(),
            ManagedMediaDomain::News->policy(),
        );
        $row = NewsArticle::factory()->create([
            'image_key' => $masterKey,
            'image_width' => 400,
            'image_height' => 200,
        ]);
        $registry = app(ManagedMediaReferenceRegistry::class);
        $reference = $registry->find(ManagedMediaDomain::News, $row->id);
        $objects = [$masterKey => $masterBytes];
        foreach ($prepared->manifest->variants as $index => $descriptor) {
            $objects[$descriptor->key] = $prepared->variants[$index]->bytes;
        }
        $objects['variants/v1/news/'.self::UUID.'/manifest.json'] = $prepared->manifest->toJson();
        $requests = [];
        $this->installS3($objects, $requests);

        $journal = app(ApplyJournal::class);
        $run = $journal->createApplyRun(
            $this->identity(),
            new ApplyRunSelection(ManagedMediaDomain::News, 0, 1, $row->id),
        );
        $item = $journal->snapshot($run, new PreflightResult(
            $reference,
            PreflightClassification::LegacyBackfillable,
            prepared: $prepared,
        ));
        $objectIds = $this->plan($journal, $item, $prepared);
        $journal->markRevalidated($item);
        $journal->commitIntent($objectIds[0]);

        $result = app(ForwardItemEvidenceBuilder::class)->build(
            $run,
            $item,
            $this->moment(),
            $this->identity(),
            ReconciliationBackendMode::S3,
        );

        $this->assertTrue($result->isAccepted());
        $this->assertSame(ReconciliationBackendMode::S3, $result->evidence->backendMode);
        $this->assertNotEmpty($requests);
        $this->assertContains('GET', array_map(fn (RequestInterface $request) => $request->getMethod(), $requests));
        foreach ($requests as $request) {
            $this->assertContains($request->getMethod(), ['HEAD', 'GET']);
            $this->assertSame('', $request->getUri()->getQuery());
            $this->assertNotSame('/private-test-bucket', rtrim($request->getUri()->getPath(), '/'));
        }
        $this->assertSame(0, DB::table('media_backfill_reconciliation_events')->count());
    }

    /** @return array<string, mixed> */
    private function candidate(bool $ambiguous = true): array
    {
        $masterKey = 'news/'.self::UUID.'.webp';
        $masterBytes = $this->fixtureBytes(400, 200, 'webp');
        Storage::disk('media_local')->put($masterKey, $masterBytes);
        $row = NewsArticle::factory()->create([
            'image_key' => $masterKey,
            'image_width' => 400,
            'image_height' => 200,
        ]);
        $registry = app(ManagedMediaReferenceRegistry::class);
        $reference = $registry->find(ManagedMediaDomain::News, $row->id);
        $preflight = app(ResponsiveBackfillPreflight::class)->inspect($reference);
        $this->assertSame(PreflightClassification::LegacyBackfillable, $preflight->classification);
        $journal = app(ApplyJournal::class);
        $run = $journal->createApplyRun(
            $this->identity(),
            new ApplyRunSelection(ManagedMediaDomain::News, 0, 1, $row->id),
        );
        $item = $journal->snapshot($run, $preflight);
        $objectIds = $this->plan($journal, $item, $preflight->prepared);
        $objectBytes = [];
        $objectKeys = [];
        foreach (DB::table('media_backfill_objects')->where('item_id', $item)->orderBy('id')->get() as $object) {
            $bytes = $object->kind === ObjectKind::Manifest->value
                ? $preflight->prepared->manifest->toJson()
                : $preflight->prepared->variants[array_search(
                    $object->object_key,
                    array_column($preflight->prepared->manifest->variants, 'key'),
                    true,
                )]->bytes;
            Storage::disk('media_local')->put($object->object_key, $bytes);
            $objectBytes[(int) $object->id] = $bytes;
            $objectKeys[(int) $object->id] = $object->object_key;
        }
        if ($ambiguous) {
            $journal->markRevalidated($item);
            $journal->commitIntent($objectIds[0]);
        }

        return compact('journal', 'run', 'item', 'row', 'reference', 'objectIds') + [
            'object_ids' => $objectIds,
            'object_bytes' => $objectBytes,
            'object_keys' => $objectKeys,
            'prepared' => $preflight->prepared,
            'master_key' => $masterKey,
            'master_bytes' => $masterBytes,
        ];
    }

    /** @return list<int> */
    private function plan(ApplyJournal $journal, int $item, object $prepared): array
    {
        $ids = [];
        foreach ($prepared->manifest->variants as $index => $descriptor) {
            $bytes = $prepared->variants[$index]->bytes;
            $ids[] = $journal->planObject($item, new TargetObject(
                $descriptor->key,
                ObjectKind::Variant,
                hash('sha256', $bytes),
                strlen($bytes),
                $descriptor->mimeType,
            ));
        }
        $manifest = $prepared->manifest->toJson();
        $itemRow = $journal->item($item);
        $ids[] = $journal->planObject($item, new TargetObject(
            $itemRow->manifest_key,
            ObjectKind::Manifest,
            hash('sha256', $manifest),
            strlen($manifest),
            'application/json',
        ));

        return $ids;
    }

    private function analysis(array $candidate): ReconciliationItemOperationalAnalysis
    {
        $objects = DB::table('media_backfill_objects')->where('item_id', $candidate['item'])->orderBy('id')->get()->all();

        return app(ReconciliationStateValidator::class)->analyzeItem(
            $candidate['journal']->run($candidate['run']),
            $candidate['journal']->item($candidate['item']),
            $objects,
        );
    }

    private function build(array $candidate): ForwardItemEvidenceBuildResult
    {
        return app(ForwardItemEvidenceBuilder::class)->build(
            $candidate['run'],
            $candidate['item'],
            $this->moment(),
            $this->identity(),
            ReconciliationBackendMode::Local,
        );
    }

    private function moment(): DateTimeImmutable
    {
        return new DateTimeImmutable('2026-09-13 10:00:00', new DateTimeZone('UTC'));
    }

    /** @return array<string, array<int, array<string, mixed>>> */
    private function journalSnapshot(): array
    {
        return [
            'runs' => DB::table('media_backfill_runs')->orderBy('run_id')->get()->map(fn ($row) => (array) $row)->all(),
            'items' => DB::table('media_backfill_items')->orderBy('id')->get()->map(fn ($row) => (array) $row)->all(),
            'objects' => DB::table('media_backfill_objects')->orderBy('id')->get()->map(fn ($row) => (array) $row)->all(),
            'events' => DB::table('media_backfill_reconciliation_events')->orderBy('event_id')->get()->map(fn ($row) => (array) $row)->all(),
        ];
    }

    /** @return array<string, string> */
    private function storageSnapshot(): array
    {
        $snapshot = [];
        foreach (File::allFiles($this->safetyRoot) as $file) {
            $key = ltrim(str_replace($this->safetyRoot, '', $file->getPathname()), '/');
            $snapshot[$key] = hash_file('sha256', $file->getPathname());
        }
        ksort($snapshot);

        return $snapshot;
    }

    /** @param array<string, string> $objects @param list<RequestInterface> $requests */
    private function installS3(array $objects, array &$requests): void
    {
        $config = [
            'driver' => 's3',
            'version' => 'latest',
            'region' => 'us-east-1',
            'bucket' => 'private-test-bucket',
            'endpoint' => 'https://s3.invalid',
            'use_path_style_endpoint' => true,
            'visibility' => 'private',
            'key' => 'test-only',
            'secret' => 'test-only',
            'retries' => 0,
            'http_handler' => function (RequestInterface $request) use ($objects, &$requests) {
                $requests[] = $request;
                $path = rawurldecode(ltrim($request->getUri()->getPath(), '/'));
                $key = str_starts_with($path, 'private-test-bucket/')
                    ? substr($path, strlen('private-test-bucket/'))
                    : '';
                if (! array_key_exists($key, $objects)) {
                    return Create::rejectionFor([
                        'exception' => new \RuntimeException('simulated missing object'),
                        'response' => new Response(404, [], '<Error><Code>NoSuchKey</Code></Error>'),
                    ]);
                }
                $bytes = $objects[$key];
                $body = $request->getMethod() === 'HEAD' ? '' : $bytes;

                return Create::promiseFor(new Response(200, [
                    'Content-Length' => (string) strlen($bytes),
                    'Content-Type' => str_ends_with($key, '.json') ? 'application/json' : 'image/webp',
                ], $body));
            },
        ];
        config()->set('media.disk', 'media_s3');
        config()->set('filesystems.disks.media_s3', $config);
        Storage::set('media_s3', Storage::build($config));
    }

    private function assertReconciliationError(ReconciliationError $reason, callable $callback): void
    {
        try {
            $callback();
            $this->fail('Expected a reconciliation error.');
        } catch (ReconciliationException $error) {
            $this->assertSame($reason, $error->reason);
            $this->assertSame($reason->value, $error->getMessage());
            $this->assertNull($error->getPrevious());
        }
    }
}
