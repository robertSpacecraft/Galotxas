<?php

namespace Tests\Feature;

use App\Models\NewsArticle;
use App\Services\Media\Backfill\ApplyItemPublisher;
use App\Services\Media\Backfill\InspectionReason;
use App\Services\Media\Backfill\ManagedMediaDomain;
use App\Services\Media\Backfill\ManagedMediaReferenceRegistry;
use App\Services\Media\Backfill\ManifestInspection;
use App\Services\Media\Backfill\ManifestInspectionState;
use App\Services\Media\Backfill\ObjectInspection;
use App\Services\Media\Backfill\ObjectInspectionState;
use App\Services\Media\Backfill\PreflightClassification;
use App\Services\Media\Backfill\PreflightResult;
use App\Services\Media\Backfill\Reconciliation\ReconciliationJournal;
use App\Services\Media\Backfill\ResponsiveBackfillPreflight;
use App\Services\Media\Backfill\ResponsiveMediaInspector;
use App\Services\Media\Backfill\Safety\AdvisoryLockHandle;
use App\Services\Media\Backfill\Safety\ApplyJournal;
use App\Services\Media\Backfill\Safety\ApplyMaintenanceGuard;
use App\Services\Media\Backfill\Safety\ApplyResult;
use App\Services\Media\Backfill\Safety\ApplyRunSelection;
use App\Services\Media\Backfill\Safety\BackfillSafetyException;
use App\Services\Media\Backfill\Safety\CleanupState;
use App\Services\Media\Backfill\Safety\CreateReceipt;
use App\Services\Media\Backfill\Safety\CreateState;
use App\Services\Media\Backfill\Safety\ExclusiveObjectCreator;
use App\Services\Media\Backfill\Safety\JournaledObjectWriter;
use App\Services\Media\Backfill\Safety\LockAcquireState;
use App\Services\Media\Backfill\Safety\MariaDbBackfillLock;
use App\Services\Media\Backfill\Safety\ObjectKind;
use App\Services\Media\Backfill\Safety\SafetyError;
use App\Services\Media\Backfill\Safety\TargetObject;
use App\Services\Media\ImagePreparationPolicy;
use App\Services\Media\ManifestImage;
use App\Services\Media\ResponsiveImageProfile;
use App\Services\Media\ResponsiveMediaKeys;
use Closure;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Mockery;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\BackfillSafetyFixtures;
use Tests\TestCase;

class BackfillApplyItemPublisherTest extends TestCase
{
    use BackfillSafetyFixtures;
    use DatabaseTruncation;

    private const UUID = '650e8400-e29b-41d4-a716-446655440000';

    protected function setUp(): void
    {
        parent::setUp();
        $this->assertSame('galotxas_testing', DB::connection()->getDatabaseName());
        $this->assertSame('test-db', DB::connection()->getConfig('host'));
        $this->assertSame(0, DB::transactionLevel());
        $this->setupSafetyStorage();
    }

    protected function tearDown(): void
    {
        try {
            $this->releaseBackfillLock();
            if (DB::transactionLevel() > 0) {
                DB::rollBack(0);
            }
            $this->truncateTablesForAllConnections();
            $this->cleanupSafetyStorage();
        } finally {
            parent::tearDown();
        }
    }

    public function test_success_plans_every_target_then_writes_variants_in_order_and_manifest_last(): void
    {
        $candidate = $this->candidate(700, 350);
        $before = $candidate['row']->fresh()->getAttributes();
        $checkpoint = $candidate['journal']->run($candidate['run'])->checkpoints_json;
        $calls = [];
        $first = true;
        $writer = new ObservingJournaledObjectWriter(
            app(JournaledObjectWriter::class),
            function (int $objectId) use (&$calls, &$first, $candidate) {
                $rows = DB::table('media_backfill_objects')->where('item_id', $candidate['item'])->orderBy('id')->get();
                if ($first) {
                    $first = false;
                    $this->assertCount(count($candidate['prepared']->variants) + 1, $rows);
                    $this->assertTrue($rows->every(fn ($row) => $row->write_state === 'planned'));
                    $this->assertSame('revalidated', $candidate['journal']->item($candidate['item'])->phase);
                }
                $calls[] = $candidate['journal']->target($objectId)->key;
            },
        );
        $lock = $this->lock();

        try {
            $result = $this->publisher(writer: $writer)->publish($candidate['run'], $candidate['item'], $lock);
        } finally {
            $lock->release();
        }

        $expectedKeys = [
            ...array_map(fn (ManifestImage $variant) => $variant->key, $candidate['prepared']->manifest->variants),
            $candidate['manifest_key'],
        ];
        $objects = DB::table('media_backfill_objects')->where('item_id', $candidate['item'])->orderBy('id')->get();
        $this->assertSame(ApplyResult::Published, $result);
        $this->assertSame($expectedKeys, $calls);
        $this->assertSame($expectedKeys, $objects->pluck('object_key')->all());
        $this->assertTrue($objects->every(fn ($object) => $object->write_state === 'created'));
        $this->assertSame(ObjectKind::Manifest->value, $objects->last()->kind);
        $this->assertSame(ApplyResult::Published->value, $candidate['journal']->item($candidate['item'])->apply_result);
        $this->assertSame($checkpoint, $candidate['journal']->run($candidate['run'])->checkpoints_json);
        $this->assertSame('active', $candidate['journal']->run($candidate['run'])->state);
        $this->assertNull($candidate['journal']->run($candidate['run'])->finished_at);
        $this->assertSame($before, $candidate['row']->fresh()->getAttributes());
        $this->assertSame($candidate['master_bytes'], Storage::disk('media_local')->get($candidate['master_key']));
        foreach ($objects as $object) {
            $this->assertTrue(Storage::disk('media_local')->fileExists($object->object_key));
        }
    }

    public function test_zero_variant_candidate_runs_second_barrier_and_publishes_manifest_only(): void
    {
        $candidate = $this->candidate(200, 100);
        $inspector = new ControllableResponsiveMediaInspector(app(ResponsiveMediaInspector::class));
        $lock = $this->lock();

        try {
            $result = $this->publisher(inspector: $inspector)->publish($candidate['run'], $candidate['item'], $lock);
        } finally {
            $lock->release();
        }

        $objects = DB::table('media_backfill_objects')->where('item_id', $candidate['item'])->get();
        $this->assertSame([], $candidate['prepared']->variants);
        $this->assertSame(ApplyResult::Published, $result);
        $this->assertCount(1, $objects);
        $this->assertSame(ObjectKind::Manifest->value, $objects[0]->kind);
        $this->assertSame($candidate['manifest_key'], $objects[0]->object_key);
        $this->assertGreaterThanOrEqual(2, $inspector->masterInspections);
        $this->assertGreaterThanOrEqual(2, $inspector->manifestInspections);
        $this->assertTrue(Storage::disk('media_local')->fileExists($candidate['manifest_key']));
    }

    #[DataProvider('firstBarrierDrifts')]
    public function test_first_revalidation_failures_are_terminal_and_write_nothing(string $case, ApplyResult $expected): void
    {
        $candidate = $this->candidate();
        if ($case === 'candidate_manifest') {
            $json = $candidate['prepared']->manifest->toJson().' ';
            DB::table('media_backfill_items')->where('id', $candidate['item'])->update([
                'candidate_manifest_json' => $json,
                'candidate_manifest_sha256' => hash('sha256', $json),
            ]);
        } elseif ($case === 'entity_missing') {
            $candidate['row']->forceDelete();
        } elseif ($case === 'reference_changed') {
            $candidate['row']->update(['image_key' => 'news/'.Str::uuid().'.webp']);
        } elseif ($case === 'ownership_changed') {
            NewsArticle::factory()->create([
                'image_key' => str_replace('.webp', '.jpg', $candidate['master_key']),
                'image_width' => 400,
                'image_height' => 200,
            ]);
        } elseif ($case === 'master_sha') {
            Storage::disk('media_local')->put($candidate['master_key'], $candidate['master_bytes']."\0");
        } elseif ($case === 'metadata') {
            $candidate['row']->update(['image_width' => 99]);
        } elseif ($case === 'manifest_residue') {
            Storage::disk('media_local')->put($candidate['manifest_key'], $candidate['prepared']->manifest->toJson());
        } elseif ($case === 'variant_residue') {
            Storage::disk('media_local')->put(
                $candidate['prepared']->manifest->variants[0]->key,
                $candidate['prepared']->variants[0]->bytes,
            );
        }
        if ($case === 'master_sha') {
            $fresh = app(ResponsiveBackfillPreflight::class)->inspect(
                app(ManagedMediaReferenceRegistry::class)->find(ManagedMediaDomain::News, $candidate['row']->id),
            );
            $this->assertSame(PreflightClassification::LegacyBackfillable, $fresh->classification);
            $this->assertNotSame($candidate['journal']->item($candidate['item'])->source_sha256, $fresh->prepared->masterSha256);
        }
        if ($case === 'candidate_manifest') {
            $fresh = app(ResponsiveBackfillPreflight::class)->inspect($candidate['reference']);
            $this->assertSame(PreflightClassification::LegacyBackfillable, $fresh->classification);
            $this->assertNotSame($candidate['journal']->item($candidate['item'])->candidate_manifest_json, $fresh->prepared->manifest->toJson());
        }
        $before = $this->storageSnapshot();
        $lock = $this->lock();

        try {
            $result = $this->publisher()->publish($candidate['run'], $candidate['item'], $lock);
        } finally {
            $lock->release();
        }

        $this->assertSame($expected, $result);
        $this->assertSame($expected->value, $candidate['journal']->item($candidate['item'])->apply_result);
        $this->assertSame($before, $this->storageSnapshot());
        $this->assertSame(0, DB::table('media_backfill_objects')->where('item_id', $candidate['item'])->count());
        $this->assertSame('{"news":0}', $candidate['journal']->run($candidate['run'])->checkpoints_json);
    }

    public static function firstBarrierDrifts(): array
    {
        return [
            'entity missing' => ['entity_missing', ApplyResult::ReferenceChanged],
            'reference changed' => ['reference_changed', ApplyResult::ReferenceChanged],
            'ownership changed' => ['ownership_changed', ApplyResult::ReferenceChanged],
            'master sha changed' => ['master_sha', ApplyResult::FailedNoWrites],
            'candidate bytes and hash changed' => ['candidate_manifest', ApplyResult::FailedNoWrites],
            'metadata no longer acceptable' => ['metadata', ApplyResult::ReferenceChanged],
            'manifest residue appeared' => ['manifest_residue', ApplyResult::FailedNoWrites],
            'variant residue appeared' => ['variant_residue', ApplyResult::FailedNoWrites],
        ];
    }

    public function test_first_preflight_inspection_failure_is_not_treated_as_absence(): void
    {
        $candidate = $this->candidate();
        $preflight = Mockery::mock(ResponsiveBackfillPreflight::class);
        $preflight->shouldReceive('inspect')->once()->andReturn(new PreflightResult(
            $candidate['reference'],
            PreflightClassification::InspectionFailed,
            [InspectionReason::TransportError],
        ));
        $lock = $this->lock();

        try {
            $result = $this->publisher(preflight: $preflight)->publish($candidate['run'], $candidate['item'], $lock);
        } finally {
            $lock->release();
        }

        $this->assertSame(ApplyResult::FailedNoWrites, $result);
        $this->assertSame(0, DB::table('media_backfill_objects')->where('item_id', $candidate['item'])->count());
        $this->assertSame($candidate['master_files'], $this->storageSnapshot());
    }

    public function test_target_inspection_failure_after_planning_writes_nothing(): void
    {
        $candidate = $this->candidate();
        $inspector = new ControllableResponsiveMediaInspector(app(ResponsiveMediaInspector::class));
        $inspector->failTargets = true;
        $lock = $this->lock();

        try {
            $result = $this->publisher(inspector: $inspector)->publish($candidate['run'], $candidate['item'], $lock);
        } finally {
            $lock->release();
        }

        $objects = DB::table('media_backfill_objects')->where('item_id', $candidate['item'])->get();
        $this->assertSame(ApplyResult::FailedNoWrites, $result);
        $this->assertCount(count($candidate['prepared']->variants) + 1, $objects);
        $this->assertTrue($objects->every(fn ($object) => $object->write_state === 'planned'));
        $this->assertSame($candidate['master_files'], $this->storageSnapshot());
    }

    #[DataProvider('invalidContexts')]
    public function test_wrong_item_run_classification_or_phase_is_rejected(string $case): void
    {
        $candidate = $this->candidate();
        $run = $candidate['run'];
        $item = $candidate['item'];
        if ($case === 'run') {
            $run = $candidate['journal']->createApplyRun(
                $this->identity(),
                new ApplyRunSelection(ManagedMediaDomain::News, 0, 1, $candidate['row']->id),
            );
        } elseif ($case === 'item') {
            $item += 999;
        } elseif ($case === 'classification') {
            DB::table('media_backfill_items')->where('id', $item)->update([
                'preflight_classification' => PreflightClassification::ResponsiveOk->value,
            ]);
        } elseif ($case === 'phase') {
            $candidate['journal']->markRevalidated($item);
        }
        $lock = $this->lock();

        try {
            $this->assertSafetyError(
                SafetyError::IllegalTransition,
                fn () => $this->publisher()->publish($run, $item, $lock),
            );
        } finally {
            $lock->release();
        }

        $this->assertSame($candidate['master_files'], $this->storageSnapshot());
    }

    public static function invalidContexts(): array
    {
        return ['wrong run' => ['run'], 'unknown item' => ['item'], 'wrong classification' => ['classification'], 'wrong phase' => ['phase']];
    }

    #[DataProvider('variantReceiptCases')]
    public function test_variant_receipts_map_exactly_and_never_retry(
        array $states,
        ApplyResult $expected,
        int $pending,
    ): void {
        $candidate = $this->candidate(700, 350);
        $creator = Mockery::mock(ExclusiveObjectCreator::class);
        $creator->shouldReceive('create')->times(count($states))->andReturn(
            ...array_map(fn (CreateState $state) => new CreateReceipt($state), $states),
        );
        $writer = new JournaledObjectWriter(
            $candidate['journal'],
            app(ApplyMaintenanceGuard::class),
            $creator,
            app('db'),
        );
        $lock = $this->lock();

        try {
            $result = $this->publisher(writer: $writer)->publish($candidate['run'], $candidate['item'], $lock);
        } finally {
            $lock->release();
        }

        $this->assertSame($expected, $result);
        $this->assertSame($expected->value, $candidate['journal']->item($candidate['item'])->apply_result);
        $this->assertSame($pending, DB::table('media_backfill_objects')->where('item_id', $candidate['item'])
            ->where('cleanup_state', CleanupState::Pending->value)->count());
        if ($expected === ApplyResult::PublicationUnknown) {
            $this->assertSame(0, DB::table('media_backfill_objects')->where('item_id', $candidate['item'])
                ->where('cleanup_state', CleanupState::Pending->value)->count());
        }
        $this->assertSame($candidate['master_files'], $this->storageSnapshot());
    }

    public static function variantReceiptCases(): array
    {
        return [
            'first rejected' => [[CreateState::Rejected], ApplyResult::CollisionDetected, 0],
            'first failed' => [[CreateState::Failed], ApplyResult::FailedNoWrites, 0],
            'first unknown' => [[CreateState::Unknown], ApplyResult::PublicationUnknown, 0],
            'rejected after created' => [[CreateState::Created, CreateState::Rejected], ApplyResult::FailedCleanupIncomplete, 1],
            'failed after created' => [[CreateState::Created, CreateState::Failed], ApplyResult::FailedCleanupIncomplete, 1],
            'unknown after created' => [[CreateState::Created, CreateState::Unknown], ApplyResult::PublicationUnknown, 0],
        ];
    }

    #[DataProvider('secondBarrierFailures')]
    public function test_second_barrier_failures_never_publish_manifest_or_delete_variants(string $case, ?SafetyError $error): void
    {
        $candidate = $this->candidate();
        $inspector = new ControllableResponsiveMediaInspector(app(ResponsiveMediaInspector::class));
        $maintenance = new ControllableMaintenanceGuard;
        $lock = $this->lock();
        $alternateRoot = null;
        $mutation = function () use (
            $case,
            $candidate,
            $inspector,
            $maintenance,
            $lock,
            &$alternateRoot,
        ) {
            if ($case === 'reference') {
                $candidate['row']->update(['image_key' => 'news/'.Str::uuid().'.webp']);
            } elseif ($case === 'ownership') {
                NewsArticle::factory()->create([
                    'image_key' => str_replace('.webp', '.jpg', $candidate['master_key']),
                    'image_width' => 400,
                    'image_height' => 200,
                ]);
            } elseif ($case === 'master') {
                Storage::disk('media_local')->put($candidate['master_key'], $candidate['master_bytes']."\0");
            } elseif ($case === 'identity') {
                $alternateRoot = sys_get_temp_dir().'/galotxas-backfill-other-'.Str::uuid();
                mkdir($alternateRoot, 0700);
                config()->set('filesystems.disks.media_local.root', $alternateRoot);
            } elseif ($case === 'maintenance') {
                $maintenance->allowed = false;
            } elseif ($case === 'lock') {
                $lock->close();
            } elseif ($case === 'manifest') {
                Storage::disk('media_local')->put($candidate['manifest_key'], 'do-not-overwrite');
            } elseif ($case === 'variant_missing') {
                Storage::disk('media_local')->delete($candidate['prepared']->manifest->variants[0]->key);
            } elseif ($case === 'variant_corrupt') {
                Storage::disk('media_local')->put($candidate['prepared']->manifest->variants[0]->key, 'corrupt');
            } elseif ($case === 'inspection') {
                $inspector->failManifest = true;
            }
        };
        $creator = new AfterCreatedVariantObjectCreator(app(ExclusiveObjectCreator::class), $mutation);
        $writer = new JournaledObjectWriter($candidate['journal'], $maintenance, $creator, app('db'));

        try {
            if ($error === null) {
                $this->assertSame(
                    ApplyResult::FailedCleanupIncomplete,
                    $this->publisher($writer, $inspector, maintenance: $maintenance)
                        ->publish($candidate['run'], $candidate['item'], $lock),
                );
            } else {
                $this->assertSafetyError(
                    $error,
                    fn () => $this->publisher($writer, $inspector, maintenance: $maintenance)
                        ->publish($candidate['run'], $candidate['item'], $lock),
                );
            }
        } finally {
            if ($case !== 'lock') {
                $lock->release();
            }
            if ($alternateRoot !== null) {
                config()->set('filesystems.disks.media_local.root', $this->safetyRoot);
                File::deleteDirectory($alternateRoot);
            }
        }

        $item = $candidate['journal']->item($candidate['item']);
        $variant = DB::table('media_backfill_objects')->where('item_id', $candidate['item'])
            ->where('kind', ObjectKind::Variant->value)->first();
        $this->assertSame(ApplyResult::FailedCleanupIncomplete->value, $item->apply_result);
        $this->assertSame(CleanupState::Pending->value, $variant->cleanup_state);
        if ($case === 'manifest') {
            $this->assertSame('do-not-overwrite', Storage::disk('media_local')->get($candidate['manifest_key']));
        } else {
            $this->assertFalse(Storage::disk('media_local')->fileExists($candidate['manifest_key']));
        }
        if (! in_array($case, ['variant_missing', 'variant_corrupt'], true)) {
            $this->assertTrue(Storage::disk('media_local')->fileExists($variant->object_key));
        }
        $this->assertSame('active', $candidate['journal']->run($candidate['run'])->state);
    }

    public static function secondBarrierFailures(): array
    {
        return [
            'reference changed' => ['reference', null],
            'ownership changed' => ['ownership', null],
            'master changed' => ['master', null],
            'identity mismatch' => ['identity', SafetyError::IdentityMismatch],
            'maintenance lost' => ['maintenance', SafetyError::MaintenanceRequired],
            'lock lost' => ['lock', SafetyError::LockLost],
            'manifest appeared' => ['manifest', null],
            'variant missing' => ['variant_missing', null],
            'variant corrupt' => ['variant_corrupt', null],
            'inspection failed' => ['inspection', null],
        ];
    }

    #[DataProvider('manifestReceiptCases')]
    public function test_manifest_receipts_after_variants_map_exactly_without_retries_or_deletion(
        CreateState $state,
        ApplyResult $expected,
        CleanupState $variantCleanup,
    ): void {
        $candidate = $this->candidate();
        $creator = Mockery::mock(ExclusiveObjectCreator::class);
        $real = app(ExclusiveObjectCreator::class);
        $creator->shouldReceive('create')->twice()->andReturnUsing(
            fn (TargetObject $target, string $bytes) => $target->kind === ObjectKind::Variant
                ? $real->create($target, $bytes)
                : new CreateReceipt($state),
        );
        $writer = new JournaledObjectWriter(
            $candidate['journal'],
            app(ApplyMaintenanceGuard::class),
            $creator,
            app('db'),
        );
        $lock = $this->lock();

        try {
            $result = $this->publisher(writer: $writer)->publish($candidate['run'], $candidate['item'], $lock);
        } finally {
            $lock->release();
        }

        $variant = DB::table('media_backfill_objects')->where('item_id', $candidate['item'])
            ->where('kind', ObjectKind::Variant->value)->first();
        $manifest = DB::table('media_backfill_objects')->where('item_id', $candidate['item'])
            ->where('kind', ObjectKind::Manifest->value)->first();
        $this->assertSame($expected, $result);
        $this->assertSame($expected->value, $candidate['journal']->item($candidate['item'])->apply_result);
        $this->assertSame($variantCleanup->value, $variant->cleanup_state);
        $this->assertSame($state->value, $manifest->create_state);
        $this->assertTrue(Storage::disk('media_local')->fileExists($variant->object_key));
        $this->assertFalse(Storage::disk('media_local')->fileExists($candidate['manifest_key']));
    }

    public static function manifestReceiptCases(): array
    {
        return [
            'rejected' => [CreateState::Rejected, ApplyResult::FailedCleanupIncomplete, CleanupState::Pending],
            'failed' => [CreateState::Failed, ApplyResult::FailedCleanupIncomplete, CleanupState::Pending],
            'unknown' => [CreateState::Unknown, ApplyResult::PublicationUnknown, CleanupState::NotRequired],
        ];
    }

    #[DataProvider('zeroVariantManifestFailures')]
    public function test_zero_variant_manifest_failures_map_without_cleanup(
        CreateState $state,
        ApplyResult $expected,
    ): void {
        $candidate = $this->candidate(200, 100);
        $creator = Mockery::mock(ExclusiveObjectCreator::class);
        $creator->shouldReceive('create')->once()->andReturn(new CreateReceipt($state));
        $writer = new JournaledObjectWriter(
            $candidate['journal'],
            app(ApplyMaintenanceGuard::class),
            $creator,
            app('db'),
        );
        $lock = $this->lock();

        try {
            $result = $this->publisher(writer: $writer)->publish($candidate['run'], $candidate['item'], $lock);
        } finally {
            $lock->release();
        }

        $this->assertSame($expected, $result);
        $this->assertSame(0, DB::table('media_backfill_objects')->where('item_id', $candidate['item'])
            ->where('cleanup_state', CleanupState::Pending->value)->count());
    }

    public static function zeroVariantManifestFailures(): array
    {
        return [
            'rejected' => [CreateState::Rejected, ApplyResult::CollisionDetected],
            'failed' => [CreateState::Failed, ApplyResult::FailedNoWrites],
            'unknown' => [CreateState::Unknown, ApplyResult::PublicationUnknown],
        ];
    }

    public function test_writer_publication_exception_preserves_intent_without_fabricating_cleanup(): void
    {
        $candidate = $this->candidate();
        $writer = new IntentThenUnknownJournaledObjectWriter($candidate['journal']);
        $lock = $this->lock();

        try {
            $result = $this->publisher(writer: $writer)->publish($candidate['run'], $candidate['item'], $lock);
        } finally {
            $lock->release();
        }

        $object = DB::table('media_backfill_objects')->where('item_id', $candidate['item'])->orderBy('id')->first();
        $this->assertSame(ApplyResult::PublicationUnknown, $result);
        $this->assertSame(1, $writer->calls);
        $this->assertSame('intent', $object->write_state);
        $this->assertNull($object->create_state);
        $this->assertSame(CleanupState::NotRequired->value, $object->cleanup_state);
        $this->assertSame(ApplyResult::PublicationUnknown->value, $candidate['journal']->item($candidate['item'])->apply_result);
        $this->assertSame($candidate['master_files'], $this->storageSnapshot());
    }

    public function test_reconciliation_attempt_freezes_publisher_before_domain_or_storage_revalidation(): void
    {
        $candidate = $this->candidate();
        $context = $this->reconciliationContext();
        app(ReconciliationJournal::class)->beginRunReconciliation(
            $candidate['run'],
            $this->reconciliationUuid(1),
            $this->reconciliationMoment(),
            $context,
        );
        $registry = Mockery::mock(ManagedMediaReferenceRegistry::class);
        $registry->shouldNotReceive('find');
        $preflight = Mockery::mock(ResponsiveBackfillPreflight::class);
        $preflight->shouldNotReceive('inspect');
        $inspector = Mockery::mock(ResponsiveMediaInspector::class);
        $inspector->shouldNotReceive('inspectManifest');
        $writer = Mockery::mock(JournaledObjectWriter::class);
        $writer->shouldNotReceive('create');
        $publisher = new ApplyItemPublisher(
            $candidate['journal'],
            $registry,
            $preflight,
            $inspector,
            app(ResponsiveMediaKeys::class),
            $writer,
            app(ApplyMaintenanceGuard::class),
            app('db'),
        );
        $before = $this->storageSnapshot();

        $this->assertSafetyError(
            SafetyError::ReconciliationRequired,
            fn () => $publisher->publish($candidate['run'], $candidate['item'], $context->lock),
        );

        $this->assertSame($before, $this->storageSnapshot());
        $this->assertSame('inspected', $candidate['journal']->item($candidate['item'])->phase);
        $this->assertSame(0, DB::table('media_backfill_objects')->count());
    }

    private function candidate(int $width = 400, int $height = 200): array
    {
        $masterKey = 'news/'.self::UUID.'.webp';
        $masterBytes = $this->fixtureBytes($width, $height, 'webp');
        Storage::disk('media_local')->put($masterKey, $masterBytes);
        $row = NewsArticle::factory()->create([
            'image_key' => $masterKey,
            'image_width' => $width,
            'image_height' => $height,
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

        return [
            'journal' => $journal,
            'run' => $run,
            'item' => $item,
            'row' => $row,
            'reference' => $reference,
            'prepared' => $preflight->prepared,
            'master_key' => $masterKey,
            'master_bytes' => $masterBytes,
            'manifest_key' => $journal->item($item)->manifest_key,
            'master_files' => $this->storageSnapshot(),
        ];
    }

    private function publisher(
        ?JournaledObjectWriter $writer = null,
        ?ResponsiveMediaInspector $inspector = null,
        ?ResponsiveBackfillPreflight $preflight = null,
        ?ApplyMaintenanceGuard $maintenance = null,
    ): ApplyItemPublisher {
        return new ApplyItemPublisher(
            app(ApplyJournal::class),
            app(ManagedMediaReferenceRegistry::class),
            $preflight ?? app(ResponsiveBackfillPreflight::class),
            $inspector ?? app(ResponsiveMediaInspector::class),
            app(ResponsiveMediaKeys::class),
            $writer ?? app(JournaledObjectWriter::class),
            $maintenance ?? app(ApplyMaintenanceGuard::class),
            app('db'),
        );
    }

    private function lock(): AdvisoryLockHandle
    {
        $acquisition = app(MariaDbBackfillLock::class)->acquire($this->identity());
        $this->assertSame(LockAcquireState::Acquired, $acquisition->state);
        $this->assertNotNull($acquisition->handle);

        return $acquisition->handle;
    }

    /** @return array<string, string> */
    private function storageSnapshot(): array
    {
        $objects = [];
        foreach (File::allFiles($this->safetyRoot) as $file) {
            $key = ltrim(str_replace($this->safetyRoot, '', $file->getPathname()), '/');
            $objects[$key] = hash_file('sha256', $file->getPathname());
        }
        ksort($objects);

        return $objects;
    }
}

class ObservingJournaledObjectWriter extends JournaledObjectWriter
{
    public function __construct(
        private readonly JournaledObjectWriter $inner,
        private readonly Closure $beforeCreate,
    ) {}

    public function create(int $objectId, string $bytes, AdvisoryLockHandle $lock): CreateReceipt
    {
        ($this->beforeCreate)($objectId);

        return $this->inner->create($objectId, $bytes, $lock);
    }
}

class AfterCreatedVariantObjectCreator extends ExclusiveObjectCreator
{
    private bool $mutated = false;

    public function __construct(
        private readonly ExclusiveObjectCreator $inner,
        private readonly Closure $afterVariant,
    ) {}

    public function create(TargetObject $target, string $bytes): CreateReceipt
    {
        $receipt = $this->inner->create($target, $bytes);
        if (! $this->mutated && $target->kind === ObjectKind::Variant && $receipt->state === CreateState::Created) {
            $this->mutated = true;
            ($this->afterVariant)();
        }

        return $receipt;
    }
}

class IntentThenUnknownJournaledObjectWriter extends JournaledObjectWriter
{
    public int $calls = 0;

    public function __construct(private readonly ApplyJournal $journal) {}

    public function create(int $objectId, string $bytes, AdvisoryLockHandle $lock): CreateReceipt
    {
        $this->calls++;
        $this->journal->commitIntent($objectId);

        throw new BackfillSafetyException(SafetyError::PublicationUnknown);
    }
}

class ControllableMaintenanceGuard extends ApplyMaintenanceGuard
{
    public bool $allowed = true;

    public function __construct() {}

    public function assertAllowed(): void
    {
        if (! $this->allowed) {
            throw new BackfillSafetyException(SafetyError::MaintenanceRequired);
        }
    }
}

class ControllableResponsiveMediaInspector extends ResponsiveMediaInspector
{
    public bool $failManifest = false;

    public bool $failTargets = false;

    public int $masterInspections = 0;

    public int $manifestInspections = 0;

    public function __construct(private readonly ResponsiveMediaInspector $inner) {}

    public function inspectMaster(string $key, ResponsiveImageProfile $profile): ObjectInspection
    {
        $this->masterInspections++;

        return $this->inner->inspectMaster($key, $profile);
    }

    public function inspectManifest(string $masterKey, ResponsiveImageProfile $profile, ImagePreparationPolicy $policy): ManifestInspection
    {
        $this->manifestInspections++;
        if ($this->failManifest) {
            return new ManifestInspection(
                ManifestInspectionState::InspectionFailed,
                reason: InspectionReason::TransportError,
            );
        }

        return $this->inner->inspectManifest($masterKey, $profile, $policy);
    }

    public function inspectTargets(string $masterKey, ResponsiveImageProfile $profile): array
    {
        if ($this->failTargets) {
            return ['failed' => new ObjectInspection(
                ObjectInspectionState::InspectionFailed,
                reason: InspectionReason::TransportError,
            )];
        }

        return $this->inner->inspectTargets($masterKey, $profile);
    }

    public function inspectVariant(ManifestImage $image, string $masterKey, ResponsiveImageProfile $profile): ObjectInspection
    {
        return $this->inner->inspectVariant($image, $masterKey, $profile);
    }

    public function matchesDescriptor(string $bytes, ManifestImage $image): bool
    {
        return $this->inner->matchesDescriptor($bytes, $image);
    }
}
