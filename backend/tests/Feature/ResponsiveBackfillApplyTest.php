<?php

namespace Tests\Feature;

use App\Models\NewsArticle;
use App\Services\Media\Backfill\ApplyInvocation;
use App\Services\Media\Backfill\ApplyItemPublisher;
use App\Services\Media\Backfill\ApplyOutcome;
use App\Services\Media\Backfill\ManagedMediaDomain;
use App\Services\Media\Backfill\ManagedMediaReference;
use App\Services\Media\Backfill\ManagedMediaReferenceRegistry;
use App\Services\Media\Backfill\PreflightClassification;
use App\Services\Media\Backfill\PreflightResult;
use App\Services\Media\Backfill\ResponsiveBackfillApply;
use App\Services\Media\Backfill\ResponsiveBackfillPreflight;
use App\Services\Media\Backfill\Safety\AdvisoryLockHandle;
use App\Services\Media\Backfill\Safety\ApplyJournal;
use App\Services\Media\Backfill\Safety\ApplyMaintenanceGuard;
use App\Services\Media\Backfill\Safety\ApplyResult;
use App\Services\Media\Backfill\Safety\BackfillSafetyException;
use App\Services\Media\Backfill\Safety\CleanupState;
use App\Services\Media\Backfill\Safety\CreateReceipt;
use App\Services\Media\Backfill\Safety\CreateState;
use App\Services\Media\Backfill\Safety\LockAcquireState;
use App\Services\Media\Backfill\Safety\LockAcquisition;
use App\Services\Media\Backfill\Safety\MariaDbBackfillLock;
use App\Services\Media\Backfill\Safety\ObjectKind;
use App\Services\Media\Backfill\Safety\RecoveryBarrierState;
use App\Services\Media\Backfill\Safety\RunState;
use App\Services\Media\Backfill\Safety\SafetyError;
use App\Services\Media\Backfill\Safety\StorageIdentity;
use App\Services\Media\Backfill\Safety\TargetObject;
use App\Services\Media\ExistingMasterPreparer;
use App\Services\Media\ResponsiveManifest;
use App\Services\Media\ResponsiveMediaKeys;
use Closure;
use Illuminate\Database\Connection;
use Illuminate\Database\DatabaseManager;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Mockery;
use PDO;
use PDOStatement;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\Concerns\BackfillSafetyFixtures;
use Tests\TestCase;
use Throwable;
use WeakReference;

class ResponsiveBackfillApplyTest extends TestCase
{
    use BackfillSafetyFixtures;
    use DatabaseTruncation;

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
            if (DB::transactionLevel() > 0) {
                DB::rollBack(0);
            }
            $this->truncateTablesForAllConnections();
            $this->cleanupSafetyStorage();
        } finally {
            parent::tearDown();
        }
    }

    #[DataProvider('invalidInvocations')]
    public function test_invalid_typed_input_creates_no_run_or_storage(int $afterId, int $limit): void
    {
        $this->assertSafetyError(
            SafetyError::InvalidInput,
            fn () => new ApplyInvocation(ManagedMediaDomain::News, $afterId, $limit),
        );

        $this->assertNoJournalOrStorage();
    }

    public static function invalidInvocations(): iterable
    {
        yield 'negative after' => [-1, 1];
        yield 'zero limit' => [0, 0];
        yield 'limit above maximum' => [0, 1001];
    }

    public function test_maintenance_absent_returns_typed_outcome_without_run_or_storage(): void
    {
        $maintenance = new SequencedApplyMaintenanceGuard([1]);
        $report = $this->coordinator(maintenance: $maintenance)->run($this->invocation());

        $this->assertSame(ApplyOutcome::MaintenanceRequired, $report->outcome);
        $this->assertNull($report->upperBound);
        $this->assertNoJournalOrStorage();
    }

    public function test_recovery_blocked_before_lock_returns_reconciliation_without_new_run(): void
    {
        $journal = new ObservingApplyJournal(app('db'));
        $journal->barrierResults = [RecoveryBarrierState::Blocked];
        $locks = Mockery::mock(MariaDbBackfillLock::class);
        $locks->shouldNotReceive('acquire');

        $report = $this->coordinator(journal: $journal, locks: $locks)->run($this->invocation());

        $this->assertSame(ApplyOutcome::ReconciliationRequired, $report->outcome);
        $this->assertNoJournalOrStorage();
    }

    public function test_untrusted_recovery_check_before_lock_requires_reconciliation(): void
    {
        $journal = new ObservingApplyJournal(app('db'));
        $journal->failBarrierCall = 1;
        $locks = Mockery::mock(MariaDbBackfillLock::class);
        $locks->shouldNotReceive('acquire');

        $report = $this->coordinator(journal: $journal, locks: $locks)->run($this->invocation());

        $this->assertSame(ApplyOutcome::ReconciliationRequired, $report->outcome);
        $this->assertNoJournalOrStorage();
    }

    public function test_advisory_lock_busy_returns_typed_outcome(): void
    {
        $held = app(MariaDbBackfillLock::class)->acquire($this->identity());
        try {
            $report = $this->coordinator()->run($this->invocation());
        } finally {
            $held->handle?->release();
        }

        $this->assertSame(ApplyOutcome::LockBusy, $report->outcome);
        $this->assertNoJournalOrStorage();
    }

    #[DataProvider('failedAcquisitions')]
    public function test_failed_or_incoherent_acquisition_returns_lock_acquire_failed(LockAcquisition $acquisition): void
    {
        $locks = Mockery::mock(MariaDbBackfillLock::class);
        $locks->shouldReceive('acquire')->once()->andReturn($acquisition);

        $report = $this->coordinator(locks: $locks)->run($this->invocation());

        $this->assertSame(ApplyOutcome::LockAcquireFailed, $report->outcome);
        $this->assertNoJournalOrStorage();
    }

    public static function failedAcquisitions(): iterable
    {
        yield 'failed' => [new LockAcquisition(LockAcquireState::Failed)];
        yield 'acquired without handle' => [new LockAcquisition(LockAcquireState::Acquired)];
    }

    public function test_post_lock_maintenance_recheck_fails_before_run_and_releases_lock(): void
    {
        $maintenance = new SequencedApplyMaintenanceGuard([2]);
        $report = $this->coordinator(maintenance: $maintenance)->run($this->invocation());

        $this->assertSame(ApplyOutcome::MaintenanceRequired, $report->outcome);
        $this->assertNoJournalOrStorage();
        $this->assertLockAvailable();
    }

    public function test_post_lock_identity_recheck_fails_before_run(): void
    {
        $alternate = sys_get_temp_dir().'/galotxas-apply-identity-'.Str::uuid();
        mkdir($alternate, 0700);
        $delegate = app(MariaDbBackfillLock::class);
        $locks = new MutatingMariaDbBackfillLock($delegate, function () use ($alternate): void {
            config()->set('filesystems.disks.media_local.root', $alternate);
        });

        try {
            $report = $this->coordinator(locks: $locks)->run($this->invocation());
        } finally {
            config()->set('filesystems.disks.media_local.root', $this->safetyRoot);
            File::deleteDirectory($alternate);
        }

        $this->assertSame(ApplyOutcome::SafeFailure, $report->outcome);
        $this->assertNoJournalOrStorage();
    }

    public function test_post_lock_recovery_recheck_blocks_before_run(): void
    {
        $journal = new ObservingApplyJournal(app('db'));
        $journal->barrierResults = [RecoveryBarrierState::Clear, RecoveryBarrierState::Blocked];

        $report = $this->coordinator(journal: $journal)->run($this->invocation());

        $this->assertSame(ApplyOutcome::ReconciliationRequired, $report->outcome);
        $this->assertNoJournalOrStorage();
        $this->assertCount(2, $journal->barrierActiveRuns);
    }

    public function test_authoritative_gate_is_repeated_after_upper_bound(): void
    {
        $journal = new ObservingApplyJournal(app('db'));
        $journal->barrierResults = [
            RecoveryBarrierState::Clear,
            RecoveryBarrierState::Clear,
            RecoveryBarrierState::Blocked,
        ];

        $report = $this->coordinator(journal: $journal)->run($this->invocation());

        $this->assertSame(ApplyOutcome::ReconciliationRequired, $report->outcome);
        $this->assertSame(0, DB::table('media_backfill_runs')->count());
        $this->assertCount(3, $journal->barrierActiveRuns);
    }

    public function test_final_maintenance_failure_terminalizes_failed_only_after_lock_ownership_is_proven(): void
    {
        $maintenance = new SequencedApplyMaintenanceGuard([4]);
        $coordinator = $this->coordinator(
            registry: new InMemoryApplyRegistry([]),
            maintenance: $maintenance,
        );

        $report = $coordinator->run($this->invocation());

        $run = DB::table('media_backfill_runs')->sole();
        $this->assertSame(ApplyOutcome::MaintenanceRequired, $report->outcome);
        $this->assertSame(RunState::Failed, $report->runState);
        $this->assertSame(RunState::Failed->value, $run->state);
        $this->assertSame(SafetyError::MaintenanceRequired->value, $run->error_code);
        $this->assertSame(1, $coordinator->finalOwnershipCalls);
        $this->assertSame(RecoveryBarrierState::Clear, app(ApplyJournal::class)->recoveryBarrier());
    }

    public function test_final_identity_drift_terminalizes_failed_only_after_lock_ownership_is_proven(): void
    {
        $alternate = sys_get_temp_dir().'/galotxas-apply-final-identity-'.Str::uuid();
        mkdir($alternate, 0700);
        config()->set('filesystems.disks.media_local.root', $alternate);
        $alternateIdentity = $this->identity();
        config()->set('filesystems.disks.media_local.root', $this->safetyRoot);
        $coordinator = $this->coordinator(registry: new InMemoryApplyRegistry([]));
        $coordinator->finalIdentity = $alternateIdentity;

        try {
            $report = $coordinator->run($this->invocation());
        } finally {
            File::deleteDirectory($alternate);
        }

        $run = DB::table('media_backfill_runs')->sole();
        $this->assertSame(ApplyOutcome::SafeFailure, $report->outcome);
        $this->assertSame(RunState::Failed, $report->runState);
        $this->assertSame(RunState::Failed->value, $run->state);
        $this->assertSame(SafetyError::IdentityMismatch->value, $run->error_code);
        $this->assertSame(1, $coordinator->finalOwnershipCalls);
        $this->assertSame(RecoveryBarrierState::Clear, app(ApplyJournal::class)->recoveryBarrier());
    }

    public function test_empty_range_below_after_id_creates_completed_run_with_null_revision(): void
    {
        $registry = new InMemoryApplyRegistry([
            $this->reference(10, PreflightClassification::ExcludedNull),
        ]);

        $report = $this->coordinator(registry: $registry)->run($this->invocation(afterId: 20));

        $run = DB::table('media_backfill_runs')->sole();
        $this->assertSame(ApplyOutcome::Success, $report->outcome);
        $this->assertSame(RunState::Completed, $report->runState);
        $this->assertSame(10, $report->upperBound);
        $this->assertSame(20, $report->checkpoint);
        $this->assertSame(0, $report->observedCount);
        $this->assertNull($run->code_revision);
        $this->assertSame('{"news":20}', $run->checkpoints_json);
        $this->assertSame(0, DB::table('media_backfill_items')->count());
        $this->assertSame([], Storage::disk('media_local')->allFiles());
    }

    public function test_empty_sparse_interval_is_audited_as_completed(): void
    {
        $registry = new InMemoryApplyRegistry([], 50);

        $report = $this->coordinator(registry: $registry)->run($this->invocation(afterId: 10));

        $this->assertSame(ApplyOutcome::Success, $report->outcome);
        $this->assertSame(50, $report->upperBound);
        $this->assertSame(10, $report->checkpoint);
        $this->assertSame('completed', DB::table('media_backfill_runs')->value('state'));
        $this->assertSame(0, DB::table('media_backfill_items')->count());
        $this->assertSame([], Storage::disk('media_local')->allFiles());
    }

    public function test_fixed_upper_bound_sparse_ids_and_limit_count_observed_references(): void
    {
        $registry = new InMemoryApplyRegistry([
            $this->reference(2, PreflightClassification::ExcludedNull),
            $this->reference(9, PreflightClassification::ExcludedNull),
        ]);
        $registry->afterUpperBound = function (InMemoryApplyRegistry $registry): void {
            $registry->rows[] = $this->reference(99, PreflightClassification::ExcludedNull);
        };

        $report = $this->coordinator(registry: $registry)->run($this->invocation(limit: 2));

        $this->assertSame(9, $report->upperBound);
        $this->assertSame(2, $report->observedCount);
        $this->assertSame([2, 9], DB::table('media_backfill_items')->orderBy('entity_id')->pluck('entity_id')->map(fn ($id) => (int) $id)->all());
        $this->assertNull($report->continuationAfterId);
    }

    public function test_limit_sets_safe_continuation_only_after_clean_completion(): void
    {
        $registry = new InMemoryApplyRegistry([
            $this->reference(1, PreflightClassification::ExcludedNull),
            $this->reference(4, PreflightClassification::ExcludedNull),
            $this->reference(8, PreflightClassification::ExcludedNull),
        ]);

        $report = $this->coordinator(registry: $registry)->run($this->invocation(limit: 2));

        $this->assertSame(2, $report->observedCount);
        $this->assertSame(4, $report->checkpoint);
        $this->assertSame(4, $report->continuationAfterId);
        $this->assertSame(ApplyOutcome::Success, $report->outcome);
    }

    public function test_all_snapshots_exist_before_first_publisher_call_and_prepared_results_are_released(): void
    {
        $registry = new InMemoryApplyRegistry([
            $this->reference(1, PreflightClassification::LegacyBackfillable),
            $this->reference(5, PreflightClassification::LegacyBackfillable),
        ]);
        $preflight = $this->preflightForRegistry($registry);
        $journal = new ObservingApplyJournal(app('db'));
        $firstPublication = true;
        $publisher = new CallbackApplyItemPublisher($journal, function (string $runId, int $itemId) use ($journal, &$firstPublication): ApplyResult {
            if ($firstPublication) {
                $firstPublication = false;
                $this->assertSame(2, DB::table('media_backfill_items')->where('run_id', $runId)->count());
                $this->assertSame(2, DB::table('media_backfill_items')->where('run_id', $runId)->where('phase', 'inspected')->count());
            }

            return $this->persistApplyResult($journal, $itemId, ApplyResult::Published);
        });

        $report = $this->coordinator($registry, $preflight, $journal, publisher: $publisher)
            ->run($this->invocation(limit: 2));
        gc_collect_cycles();

        $this->assertSame(ApplyOutcome::Success, $report->outcome);
        $this->assertSame([1, 5], $publisher->entityCalls);
        foreach ($preflight->resultReferences as $weakReference) {
            $this->assertNull($weakReference->get());
        }
        $this->assertSame([], Storage::disk('media_local')->allFiles());
    }

    public function test_heartbeats_are_event_driven_and_barrier_is_never_called_for_active_own_run(): void
    {
        $registry = new InMemoryApplyRegistry([
            $this->reference(1, PreflightClassification::ExcludedNull),
        ]);
        $journal = new ObservingApplyJournal(app('db'));

        $report = $this->coordinator(registry: $registry, journal: $journal)->run($this->invocation());

        $this->assertSame(ApplyOutcome::Success, $report->outcome);
        $this->assertGreaterThanOrEqual(4, $journal->heartbeatCalls);
        $this->assertNotEmpty($journal->barrierActiveRuns);
        $this->assertSame([], array_values(array_filter($journal->barrierActiveRuns)));
    }

    public function test_late_first_pass_blocker_terminalizes_every_item_without_publication_or_checkpoint(): void
    {
        $registry = new InMemoryApplyRegistry([
            $this->reference(1, PreflightClassification::ExcludedNull),
            $this->reference(3, PreflightClassification::InvalidReference),
            $this->reference(8, PreflightClassification::LegacyBackfillable),
        ]);
        $preflight = $this->preflightForRegistry($registry);
        $journal = new ObservingApplyJournal(app('db'));
        $publisher = new CallbackApplyItemPublisher($journal, fn () => throw new RuntimeException('must not publish'));
        $before = Storage::disk('media_local')->allFiles();

        $report = $this->coordinator($registry, $preflight, $journal, publisher: $publisher)->run($this->invocation());

        $items = DB::table('media_backfill_items')->orderBy('entity_id')->get();
        $this->assertSame(ApplyOutcome::FirstPassBlocked, $report->outcome);
        $this->assertSame(RunState::Failed, $report->runState);
        $this->assertSame(3, $report->observedCount);
        $this->assertSame(['skipped', 'failed_no_writes', 'failed_no_writes'], $items->pluck('apply_result')->all());
        $this->assertSame('{"news":0}', DB::table('media_backfill_runs')->value('checkpoints_json'));
        $this->assertSame(0, $publisher->calls);
        $this->assertSame($before, Storage::disk('media_local')->allFiles());
        $this->assertSame(RecoveryBarrierState::Clear, app(ApplyJournal::class)->recoveryBarrier());
    }

    public function test_normal_skip_and_candidate_success_complete_with_checkpoint_progress(): void
    {
        $registry = new InMemoryApplyRegistry([
            $this->reference(2, PreflightClassification::ResponsiveOk),
            $this->reference(7, PreflightClassification::LegacyBackfillable),
        ]);
        $preflight = $this->preflightForRegistry($registry);
        $journal = new ObservingApplyJournal(app('db'));
        $publisher = new CallbackApplyItemPublisher(
            $journal,
            fn (string $runId, int $itemId): ApplyResult => $this->persistApplyResult($journal, $itemId, ApplyResult::Published),
        );

        $report = $this->coordinator($registry, $preflight, $journal, publisher: $publisher)->run($this->invocation());

        $this->assertSame(ApplyOutcome::Success, $report->outcome);
        $this->assertSame(7, $report->checkpoint);
        $this->assertSame(1, $report->resultCounts['skipped']);
        $this->assertSame(1, $report->resultCounts['published']);
        $this->assertSame(['skipped', 'published'], DB::table('media_backfill_items')->orderBy('entity_id')->pluck('apply_result')->all());
        $summary = json_decode(DB::table('media_backfill_runs')->value('summary_json'), true, 16, JSON_THROW_ON_ERROR);
        $this->assertSame(1, $summary[PreflightClassification::ResponsiveOk->value]);
        $this->assertSame(1, $summary[PreflightClassification::LegacyBackfillable->value]);
        $this->assertSame(1, $summary[ApplyResult::Skipped->value]);
        $this->assertSame(1, $summary[ApplyResult::Published->value]);
    }

    public function test_b2_skipped_is_safe_terminal_and_processing_continues(): void
    {
        $registry = new InMemoryApplyRegistry([
            $this->reference(3, PreflightClassification::LegacyBackfillable),
            $this->reference(7, PreflightClassification::LegacyBackfillable),
        ]);
        $preflight = $this->preflightForRegistry($registry);
        $journal = new ObservingApplyJournal(app('db'));
        $results = [ApplyResult::Skipped, ApplyResult::Published];
        $publisher = new CallbackApplyItemPublisher(
            $journal,
            function (string $runId, int $itemId) use ($journal, &$results): ApplyResult {
                return $this->persistApplyResult($journal, $itemId, array_shift($results));
            },
        );

        $report = $this->coordinator($registry, $preflight, $journal, publisher: $publisher)->run($this->invocation());

        $this->assertSame(ApplyOutcome::Success, $report->outcome);
        $this->assertSame([3, 7], $publisher->entityCalls);
        $this->assertSame([3, 7], $journal->checkpointCalls);
        $this->assertSame(7, $report->checkpoint);
    }

    public function test_real_b2_publisher_is_the_only_component_that_writes_media(): void
    {
        $masterKey = 'news/'.Str::uuid().'.webp';
        $masterBytes = $this->fixtureBytes(700, 350, 'webp');
        Storage::disk('media_local')->put($masterKey, $masterBytes);
        $article = NewsArticle::factory()->create([
            'image_key' => $masterKey,
            'image_width' => 700,
            'image_height' => 350,
        ]);
        $before = Storage::disk('media_local')->allFiles();
        $journal = app(ApplyJournal::class);

        $report = $this->coordinator(
            registry: app(ManagedMediaReferenceRegistry::class),
            preflight: app(ResponsiveBackfillPreflight::class),
            journal: $journal,
            publisher: app(ApplyItemPublisher::class),
        )->run($this->invocation(limit: 1));

        $objects = DB::table('media_backfill_objects')->orderBy('id')->get();
        $this->assertSame(ApplyOutcome::Success, $report->outcome);
        $this->assertSame($article->id, $report->checkpoint);
        $this->assertSame(ApplyResult::Published->value, DB::table('media_backfill_items')->value('apply_result'));
        $this->assertGreaterThan(count($before), count(Storage::disk('media_local')->allFiles()));
        $this->assertSame($masterBytes, Storage::disk('media_local')->get($masterKey));
        $this->assertSame(ObjectKind::Manifest->value, $objects->last()->kind);
        $this->assertTrue($objects->every(
            fn ($object): bool => Storage::disk('media_local')->fileExists($object->object_key),
        ));
    }

    public function test_multiple_candidates_complete_in_ascending_order_with_monotonic_checkpoint(): void
    {
        $registry = new InMemoryApplyRegistry([
            $this->reference(3, PreflightClassification::LegacyBackfillable),
            $this->reference(11, PreflightClassification::LegacyBackfillable),
            $this->reference(20, PreflightClassification::LegacyBackfillable),
        ]);
        $preflight = $this->preflightForRegistry($registry);
        $journal = new ObservingApplyJournal(app('db'));
        $publisher = new CallbackApplyItemPublisher(
            $journal,
            fn (string $runId, int $itemId): ApplyResult => $this->persistApplyResult($journal, $itemId, ApplyResult::Published),
        );

        $report = $this->coordinator($registry, $preflight, $journal, publisher: $publisher)->run($this->invocation());

        $this->assertSame(ApplyOutcome::Success, $report->outcome);
        $this->assertSame([3, 11, 20], $publisher->entityCalls);
        $this->assertSame([3, 11, 20], $journal->checkpointCalls);
        $this->assertSame(20, $report->checkpoint);
    }

    #[DataProvider('safeStopResults')]
    public function test_safe_b2_result_stops_and_only_advances_through_current_item(ApplyResult $failure): void
    {
        $registry = new InMemoryApplyRegistry([
            $this->reference(2, PreflightClassification::ExcludedNull),
            $this->reference(5, PreflightClassification::LegacyBackfillable),
            $this->reference(9, PreflightClassification::LegacyBackfillable),
            $this->reference(12, PreflightClassification::ExcludedDeleted),
        ]);
        $preflight = $this->preflightForRegistry($registry);
        $journal = new ObservingApplyJournal(app('db'));
        $publisher = new CallbackApplyItemPublisher(
            $journal,
            fn (string $runId, int $itemId): ApplyResult => $this->persistApplyResult($journal, $itemId, $failure),
        );

        $report = $this->coordinator($registry, $preflight, $journal, publisher: $publisher)->run($this->invocation());

        $items = DB::table('media_backfill_items')->orderBy('entity_id')->get();
        $this->assertSame(ApplyOutcome::SafeFailure, $report->outcome);
        $this->assertSame(RunState::Failed, $report->runState);
        $this->assertSame(5, $report->checkpoint);
        $this->assertSame([2, 5], $journal->checkpointCalls);
        $this->assertSame(1, $publisher->calls);
        $this->assertSame('failed_no_writes', $items->firstWhere('entity_id', 9)->apply_result);
        $this->assertSame('skipped', $items->firstWhere('entity_id', 12)->apply_result);
        $this->assertNull($report->continuationAfterId);
        $this->assertSame(RecoveryBarrierState::Clear, app(ApplyJournal::class)->recoveryBarrier());
    }

    public static function safeStopResults(): iterable
    {
        yield 'reference changed' => [ApplyResult::ReferenceChanged];
        yield 'collision detected' => [ApplyResult::CollisionDetected];
        yield 'failed no writes' => [ApplyResult::FailedNoWrites];
    }

    #[DataProvider('d2Results')]
    public function test_d2_b2_result_stops_without_advancing_current_or_later_items(
        ApplyResult $failure,
        RunState $expectedState,
    ): void {
        $registry = new InMemoryApplyRegistry([
            $this->reference(4, PreflightClassification::LegacyBackfillable),
            $this->reference(8, PreflightClassification::LegacyBackfillable),
        ]);
        $preflight = $this->preflightForRegistry($registry);
        $journal = new ObservingApplyJournal(app('db'));
        $publisher = new CallbackApplyItemPublisher(
            $journal,
            fn (string $runId, int $itemId): ApplyResult => $this->persistApplyResult($journal, $itemId, $failure),
        );

        $report = $this->coordinator($registry, $preflight, $journal, publisher: $publisher)->run($this->invocation());

        $this->assertSame(ApplyOutcome::ReconciliationRequired, $report->outcome);
        $this->assertSame($expectedState, $report->runState);
        $this->assertSame(0, $report->checkpoint);
        $this->assertSame([], $journal->checkpointCalls);
        $this->assertSame(1, $publisher->calls);
        $this->assertSame('failed_no_writes', DB::table('media_backfill_items')->where('entity_id', 8)->value('apply_result'));
        $this->assertSame(RecoveryBarrierState::Blocked, app(ApplyJournal::class)->recoveryBarrier());
    }

    public static function d2Results(): iterable
    {
        yield 'cleanup incomplete' => [ApplyResult::FailedCleanupIncomplete, RunState::Failed];
        yield 'publication unknown' => [ApplyResult::PublicationUnknown, RunState::Interrupted];
    }

    public function test_failed_compensated_is_a_contract_violation_without_checkpoint_or_continuation(): void
    {
        $registry = new InMemoryApplyRegistry([
            $this->reference(4, PreflightClassification::LegacyBackfillable),
            $this->reference(8, PreflightClassification::LegacyBackfillable),
        ]);
        $preflight = $this->preflightForRegistry($registry);
        $journal = new ObservingApplyJournal(app('db'));
        $publisher = new CallbackApplyItemPublisher(
            $journal,
            fn (string $runId, int $itemId): ApplyResult => $this->persistApplyResult($journal, $itemId, ApplyResult::FailedCompensated),
        );

        $report = $this->coordinator($registry, $preflight, $journal, publisher: $publisher)->run($this->invocation());

        $this->assertSame(ApplyOutcome::ReconciliationRequired, $report->outcome);
        $this->assertSame(RunState::Interrupted, $report->runState);
        $this->assertSame(0, $report->checkpoint);
        $this->assertNull($report->continuationAfterId);
        $this->assertSame([], Storage::disk('media_local')->allFiles());
    }

    public function test_b2_exception_routes_by_already_terminal_durable_result(): void
    {
        $registry = new InMemoryApplyRegistry([
            $this->reference(5, PreflightClassification::LegacyBackfillable),
            $this->reference(9, PreflightClassification::LegacyBackfillable),
        ]);
        $preflight = $this->preflightForRegistry($registry);
        $journal = new ObservingApplyJournal(app('db'));
        $publisher = new CallbackApplyItemPublisher($journal, function (string $runId, int $itemId) use ($journal): never {
            $this->persistApplyResult($journal, $itemId, ApplyResult::FailedNoWrites);
            throw new BackfillSafetyException(SafetyError::MaintenanceRequired);
        });

        $report = $this->coordinator($registry, $preflight, $journal, publisher: $publisher)->run($this->invocation());

        $this->assertSame(ApplyOutcome::MaintenanceRequired, $report->outcome);
        $this->assertSame(RunState::Failed, $report->runState);
        $this->assertSame(5, $report->checkpoint);
        $this->assertSame('failed_no_writes', DB::table('media_backfill_items')->where('entity_id', 9)->value('apply_result'));
        $this->assertSame(1, $publisher->calls);
    }

    public function test_b2_exception_with_unfinished_intent_is_not_retried_and_requires_reconciliation(): void
    {
        $registry = new InMemoryApplyRegistry([
            $this->reference(5, PreflightClassification::LegacyBackfillable),
            $this->reference(9, PreflightClassification::LegacyBackfillable),
        ]);
        $preflight = $this->preflightForRegistry($registry);
        $journal = new ObservingApplyJournal(app('db'));
        $publisher = new CallbackApplyItemPublisher($journal, function (string $runId, int $itemId) use ($journal): never {
            $this->persistUnfinishedIntent($journal, $itemId);
            throw new BackfillSafetyException(SafetyError::PublicationUnknown);
        });

        $report = $this->coordinator($registry, $preflight, $journal, publisher: $publisher)->run($this->invocation());

        $this->assertSame(ApplyOutcome::ReconciliationRequired, $report->outcome);
        $this->assertSame(RunState::Interrupted, $report->runState);
        $this->assertSame(1, $publisher->calls);
        $this->assertSame('writing', DB::table('media_backfill_items')->where('entity_id', 5)->value('phase'));
        $this->assertSame('failed_no_writes', DB::table('media_backfill_items')->where('entity_id', 9)->value('apply_result'));
        $this->assertSame(RecoveryBarrierState::Blocked, app(ApplyJournal::class)->recoveryBarrier());
    }

    public function test_arbitrary_throwable_before_publication_terminalizes_safe_evidence(): void
    {
        $registry = new InMemoryApplyRegistry([
            $this->reference(1, PreflightClassification::ExcludedNull),
            $this->reference(2, PreflightClassification::ExcludedNull),
        ]);
        $preflight = new CallbackApplyPreflight(function (ManagedMediaReference $reference): PreflightResult {
            if ($reference->id === 2) {
                throw new RuntimeException('private preflight failure');
            }

            return $this->preflightResult($reference, PreflightClassification::ExcludedNull);
        });

        $report = $this->coordinator($registry, $preflight)->run($this->invocation());

        $this->assertSame(ApplyOutcome::SafeFailure, $report->outcome);
        $this->assertSame(RunState::Failed, $report->runState);
        $this->assertSame(2, $report->observedCount);
        $this->assertSame('skipped', DB::table('media_backfill_items')->value('apply_result'));
        $this->assertSame(RecoveryBarrierState::Clear, app(ApplyJournal::class)->recoveryBarrier());
        $this->assertSame([], Storage::disk('media_local')->allFiles());
    }

    public function test_arbitrary_throwable_after_publication_boundary_is_reconciliation_required(): void
    {
        $registry = new InMemoryApplyRegistry([
            $this->reference(5, PreflightClassification::LegacyBackfillable),
            $this->reference(9, PreflightClassification::LegacyBackfillable),
        ]);
        $preflight = $this->preflightForRegistry($registry);
        $journal = new ObservingApplyJournal(app('db'));
        $publisher = new CallbackApplyItemPublisher($journal, function (string $runId, int $itemId) use ($journal): never {
            $this->persistUnfinishedIntent($journal, $itemId);
            throw new RuntimeException('private publisher failure');
        });

        $report = $this->coordinator($registry, $preflight, $journal, publisher: $publisher)->run($this->invocation());

        $this->assertSame(ApplyOutcome::ReconciliationRequired, $report->outcome);
        $this->assertSame(RunState::Interrupted, $report->runState);
        $this->assertSame(1, $publisher->calls);
        $this->assertSame(RecoveryBarrierState::Blocked, app(ApplyJournal::class)->recoveryBarrier());
    }

    public function test_finish_run_failure_preserves_untrusted_state_and_requires_reconciliation(): void
    {
        $registry = new InMemoryApplyRegistry([]);
        $journal = new ObservingApplyJournal(app('db'));
        $journal->failFinishRun = true;

        $report = $this->coordinator(registry: $registry, journal: $journal)->run($this->invocation());

        $this->assertSame(ApplyOutcome::ReconciliationRequired, $report->outcome);
        $this->assertNull($report->runState);
        $this->assertSame('active', DB::table('media_backfill_runs')->value('state'));
    }

    public function test_safe_terminal_run_requires_a_clear_post_terminal_barrier(): void
    {
        $journal = new ObservingApplyJournal(app('db'));
        $journal->barrierResults = [
            RecoveryBarrierState::Clear,
            RecoveryBarrierState::Clear,
            RecoveryBarrierState::Clear,
            RecoveryBarrierState::Blocked,
        ];

        $report = $this->coordinator(
            registry: new InMemoryApplyRegistry([]),
            journal: $journal,
        )->run($this->invocation());

        $this->assertSame(ApplyOutcome::ReconciliationRequired, $report->outcome);
        $this->assertSame(RunState::Completed, $report->runState);
        $this->assertSame('completed', DB::table('media_backfill_runs')->value('state'));
        $this->assertSame([false, false, false, false], $journal->barrierActiveRuns);
    }

    public function test_item_terminalization_failure_is_reconciliation_required(): void
    {
        $registry = new InMemoryApplyRegistry([
            $this->reference(1, PreflightClassification::ExcludedNull),
        ]);
        $journal = new ObservingApplyJournal(app('db'));
        $journal->failFinishItem = true;

        $report = $this->coordinator(registry: $registry, journal: $journal)->run($this->invocation());

        $this->assertSame(ApplyOutcome::ReconciliationRequired, $report->outcome);
        $this->assertNull($report->runState);
        $this->assertSame('active', DB::table('media_backfill_runs')->value('state'));
        $this->assertSame('inspected', DB::table('media_backfill_items')->value('phase'));
    }

    public function test_final_lock_loss_keeps_clean_run_active_and_releases_exactly_once(): void
    {
        [$locks, $handleReleaseCalls] = $this->lockManagerWithObservedRelease();
        $journal = new ObservingApplyJournal(app('db'));
        $coordinator = $this->coordinator(
            registry: new InMemoryApplyRegistry([]),
            journal: $journal,
            locks: $locks,
        );
        $coordinator->finalOwnershipFailure = new BackfillSafetyException(SafetyError::LockLost);

        $report = $coordinator->run($this->invocation());

        $this->assertSame(ApplyOutcome::ReconciliationRequired, $report->outcome);
        $this->assertNull($report->runState);
        $this->assertSame(0, $journal->finishRunCalls);
        $this->assertSame(RunState::Active->value, DB::table('media_backfill_runs')->value('state'));
        $this->assertSame(1, $coordinator->finalOwnershipCalls);
        $this->assertSame(1, $coordinator->releaseCalls);
        $this->assertSame(1, $handleReleaseCalls());
        $this->assertSame(RecoveryBarrierState::Blocked, app(ApplyJournal::class)->recoveryBarrier());
    }

    public function test_untrusted_final_lock_check_keeps_clean_run_active_and_releases_exactly_once(): void
    {
        [$locks, $handleReleaseCalls] = $this->lockManagerWithObservedRelease();
        $journal = new ObservingApplyJournal(app('db'));
        $coordinator = $this->coordinator(
            registry: new InMemoryApplyRegistry([]),
            journal: $journal,
            locks: $locks,
        );
        $coordinator->finalOwnershipFailure = new RuntimeException('private ownership failure');

        $report = $coordinator->run($this->invocation());

        $this->assertSame(ApplyOutcome::ReconciliationRequired, $report->outcome);
        $this->assertNull($report->runState);
        $this->assertSame(0, $journal->finishRunCalls);
        $this->assertSame(RunState::Active->value, DB::table('media_backfill_runs')->value('state'));
        $this->assertSame(1, $coordinator->finalOwnershipCalls);
        $this->assertSame(1, $coordinator->releaseCalls);
        $this->assertSame(1, $handleReleaseCalls());
        $this->assertSame(RecoveryBarrierState::Blocked, app(ApplyJournal::class)->recoveryBarrier());
    }

    public function test_d2_result_with_final_lock_loss_is_not_terminalized_or_retried(): void
    {
        [$locks, $handleReleaseCalls] = $this->lockManagerWithObservedRelease();
        $registry = new InMemoryApplyRegistry([
            $this->reference(5, PreflightClassification::LegacyBackfillable),
        ]);
        $preflight = $this->preflightForRegistry($registry);
        $journal = new ObservingApplyJournal(app('db'));
        $publisher = new CallbackApplyItemPublisher(
            $journal,
            fn (string $runId, int $itemId): ApplyResult => $this->persistApplyResult(
                $journal,
                $itemId,
                ApplyResult::FailedCleanupIncomplete,
            ),
        );
        $coordinator = $this->coordinator($registry, $preflight, $journal, $locks, publisher: $publisher);
        $coordinator->finalOwnershipFailure = new BackfillSafetyException(SafetyError::LockLost);

        $report = $coordinator->run($this->invocation());

        $this->assertSame(ApplyOutcome::ReconciliationRequired, $report->outcome);
        $this->assertNull($report->runState);
        $this->assertSame(0, $journal->finishRunCalls);
        $this->assertSame(RunState::Active->value, DB::table('media_backfill_runs')->value('state'));
        $this->assertSame(ApplyResult::FailedCleanupIncomplete->value, DB::table('media_backfill_items')->value('apply_result'));
        $this->assertSame(0, $report->checkpoint);
        $this->assertSame(1, $publisher->calls);
        $this->assertSame(1, $coordinator->releaseCalls);
        $this->assertSame(1, $handleReleaseCalls());
        $this->assertSame([], Storage::disk('media_local')->allFiles());
        $this->assertSame(RecoveryBarrierState::Blocked, app(ApplyJournal::class)->recoveryBarrier());
    }

    public function test_release_is_attempted_exactly_once_after_clean_completion(): void
    {
        [$locks, $releaseCalls] = $this->lockManagerWithObservedRelease();

        $report = $this->coordinator(registry: new InMemoryApplyRegistry([]), locks: $locks)
            ->run($this->invocation());

        $this->assertSame(ApplyOutcome::Success, $report->outcome);
        $this->assertSame(1, $releaseCalls());
    }

    public function test_release_failure_converts_clean_result_to_safe_failure_without_reopening_run(): void
    {
        [$locks, $releaseCalls] = $this->lockManagerWithObservedRelease(failRelease: true);

        $report = $this->coordinator(registry: new InMemoryApplyRegistry([]), locks: $locks)
            ->run($this->invocation());

        $this->assertSame(ApplyOutcome::SafeFailure, $report->outcome);
        $this->assertSame(RunState::Completed, $report->runState);
        $this->assertSame('completed', DB::table('media_backfill_runs')->value('state'));
        $this->assertSame(1, $releaseCalls());
    }

    public function test_release_failure_never_overrides_reconciliation_required(): void
    {
        [$locks, $releaseCalls] = $this->lockManagerWithObservedRelease(failRelease: true);
        $registry = new InMemoryApplyRegistry([
            $this->reference(5, PreflightClassification::LegacyBackfillable),
        ]);
        $preflight = $this->preflightForRegistry($registry);
        $journal = new ObservingApplyJournal(app('db'));
        $publisher = new CallbackApplyItemPublisher(
            $journal,
            fn (string $runId, int $itemId): ApplyResult => $this->persistApplyResult($journal, $itemId, ApplyResult::PublicationUnknown),
        );

        $report = $this->coordinator($registry, $preflight, $journal, $locks, publisher: $publisher)
            ->run($this->invocation());

        $this->assertSame(ApplyOutcome::ReconciliationRequired, $report->outcome);
        $this->assertSame(1, $releaseCalls());
    }

    public function test_report_is_bounded_to_safe_facts_and_coordinator_has_no_direct_writer_dependency(): void
    {
        $secret = 'news/00000000-0000-4000-8000-000000000001.webp?credential=private';
        $registry = new InMemoryApplyRegistry([
            new ManagedMediaReference(ManagedMediaDomain::News, 1, $secret),
        ]);
        $preflight = new CallbackApplyPreflight(fn (ManagedMediaReference $reference): PreflightResult => new PreflightResult(
            $reference,
            PreflightClassification::InvalidReference,
        ));

        $report = $this->coordinator($registry, $preflight)->run($this->invocation());
        $serialized = serialize($report);
        $source = file_get_contents(app_path('Services/Media/Backfill/ResponsiveBackfillApply.php'));

        $this->assertSame(ApplyOutcome::FirstPassBlocked, $report->outcome);
        $this->assertStringNotContainsString($secret, $serialized);
        $this->assertStringNotContainsString('candidate_manifest_json', $serialized);
        $this->assertStringNotContainsString('storage_identity', $serialized);
        $this->assertStringNotContainsString('JournaledObjectWriter', $source);
        $this->assertStringNotContainsString('ExclusiveObjectCreator', $source);
        $this->assertStringNotContainsString('delete(', $source);
        $this->assertStringNotContainsString('resume', strtolower($source));
    }

    private function coordinator(
        ?ManagedMediaReferenceRegistry $registry = null,
        ?ResponsiveBackfillPreflight $preflight = null,
        ?ApplyJournal $journal = null,
        ?MariaDbBackfillLock $locks = null,
        ?ApplyMaintenanceGuard $maintenance = null,
        ?ApplyItemPublisher $publisher = null,
    ): ControllableResponsiveBackfillApply {
        $journal ??= new ObservingApplyJournal(app('db'));
        $registry ??= new InMemoryApplyRegistry([]);
        $preflight ??= $this->preflightForRegistry($registry);
        $publisher ??= new CallbackApplyItemPublisher(
            $journal,
            fn (string $runId, int $itemId): ApplyResult => $this->persistApplyResult($journal, $itemId, ApplyResult::Published),
        );

        return new ControllableResponsiveBackfillApply(
            $maintenance ?? app(ApplyMaintenanceGuard::class),
            app('db'),
            $journal,
            $locks ?? app(MariaDbBackfillLock::class),
            $registry,
            $preflight,
            $publisher,
        );
    }

    private function invocation(int $afterId = 0, int $limit = 1000): ApplyInvocation
    {
        return new ApplyInvocation(ManagedMediaDomain::News, $afterId, $limit);
    }

    private function reference(int $id, PreflightClassification $classification): ManagedMediaReference
    {
        $key = match ($classification) {
            PreflightClassification::ExcludedNull => null,
            PreflightClassification::InvalidReference => '../invalid-'.$id,
            PreflightClassification::ResponsiveOk => 'news/00000000-0000-4000-8000-'.str_pad((string) $id, 12, '0', STR_PAD_LEFT).'.jpg',
            default => 'news/00000000-0000-4000-8000-'.str_pad((string) $id, 12, '0', STR_PAD_LEFT).'.webp',
        };

        return new ManagedMediaReference(
            ManagedMediaDomain::News,
            $id,
            $key,
            $classification === PreflightClassification::ExcludedDeleted,
        );
    }

    private function preflightForRegistry(ManagedMediaReferenceRegistry $registry): CallbackApplyPreflight
    {
        return new CallbackApplyPreflight(function (ManagedMediaReference $reference) use ($registry): PreflightResult {
            $classification = $registry instanceof InMemoryApplyRegistry
                ? $registry->classification($reference->id)
                : PreflightClassification::ExcludedNull;

            return $this->preflightResult($reference, $classification);
        });
    }

    private function preflightResult(
        ManagedMediaReference $reference,
        PreflightClassification $classification,
    ): PreflightResult {
        if ($classification !== PreflightClassification::LegacyBackfillable) {
            return new PreflightResult($reference, $classification);
        }

        $prepared = app(ExistingMasterPreparer::class)->prepare(
            $reference->masterKey,
            $this->fixtureBytes(400, 200, 'webp'),
            $reference->domain->profile(),
            $reference->domain->policy(),
        );

        return new PreflightResult($reference, $classification, prepared: $prepared);
    }

    private function persistApplyResult(ApplyJournal $journal, int $itemId, ApplyResult $result): ApplyResult
    {
        if (in_array($result, [
            ApplyResult::Skipped,
            ApplyResult::ReferenceChanged,
            ApplyResult::FailedNoWrites,
        ], true)) {
            $journal->finishItem($itemId, $result);

            return $result;
        }

        $item = $journal->item($itemId);
        $objects = [];
        if ($result === ApplyResult::Published) {
            $manifest = ResponsiveManifest::fromJson(
                $item->candidate_manifest_json,
                $item->master_key,
                $item->manifest_key,
                app(ResponsiveMediaKeys::class),
            );
            foreach ($manifest->variants as $variant) {
                $objects[] = $journal->planObject($itemId, new TargetObject(
                    $variant->key,
                    ObjectKind::Variant,
                    hash('sha256', $variant->key),
                    $variant->size,
                    $variant->mimeType,
                ));
            }
        }
        $objects[] = $journal->planObject($itemId, new TargetObject(
            $item->manifest_key,
            ObjectKind::Manifest,
            $item->candidate_manifest_sha256,
            strlen($item->candidate_manifest_json),
            'application/json',
        ));
        $journal->markRevalidated($itemId);

        foreach ($objects as $objectId) {
            $journal->commitIntent($objectId);
            $receipt = match ($result) {
                ApplyResult::CollisionDetected => new CreateReceipt(CreateState::Rejected),
                ApplyResult::PublicationUnknown => new CreateReceipt(CreateState::Unknown),
                default => new CreateReceipt(CreateState::Created),
            };
            $journal->recordReceipt($objectId, $receipt);

            if ($result === ApplyResult::FailedCleanupIncomplete) {
                $journal->updateCleanup($objectId, CleanupState::Pending);
            } elseif ($result === ApplyResult::FailedCompensated) {
                $journal->updateCleanup($objectId, CleanupState::Pending);
                $journal->updateCleanup($objectId, CleanupState::Deleted);
            }
        }
        $journal->finishItem($itemId, $result);

        return $result;
    }

    private function persistUnfinishedIntent(ApplyJournal $journal, int $itemId): void
    {
        $item = $journal->item($itemId);
        $objectId = $journal->planObject($itemId, new TargetObject(
            $item->manifest_key,
            ObjectKind::Manifest,
            $item->candidate_manifest_sha256,
            strlen($item->candidate_manifest_json),
            'application/json',
        ));
        $journal->markRevalidated($itemId);
        $journal->commitIntent($objectId);
    }

    private function assertNoJournalOrStorage(): void
    {
        $this->assertSame(0, DB::table('media_backfill_runs')->count());
        $this->assertSame(0, DB::table('media_backfill_items')->count());
        $this->assertSame(0, DB::table('media_backfill_objects')->count());
        $this->assertSame([], Storage::disk('media_local')->allFiles());
    }

    private function assertLockAvailable(): void
    {
        $acquisition = app(MariaDbBackfillLock::class)->acquire($this->identity());
        try {
            $this->assertSame(LockAcquireState::Acquired, $acquisition->state);
        } finally {
            $acquisition->handle?->release();
        }
    }

    /** @return array{MariaDbBackfillLock, Closure(): int} */
    private function lockManagerWithObservedRelease(bool $failRelease = false): array
    {
        $identity = $this->identity();
        $releaseCalls = 0;
        $connection = Mockery::mock(Connection::class);
        $connection->shouldReceive('disconnect')->once();
        $pdo = Mockery::mock(PDO::class);
        $pdo->shouldReceive('query')->with('SELECT CONNECTION_ID()')->zeroOrMoreTimes()->andReturnUsing(function () {
            $statement = Mockery::mock(PDOStatement::class);
            $statement->shouldReceive('fetchColumn')->once()->andReturn('701');

            return $statement;
        });
        $pdo->shouldReceive('prepare')->with('SELECT IS_USED_LOCK(?)')->zeroOrMoreTimes()->andReturnUsing(function () {
            $statement = Mockery::mock(PDOStatement::class);
            $statement->shouldReceive('execute')->once()->andReturn(true);
            $statement->shouldReceive('fetchColumn')->once()->andReturn('701');

            return $statement;
        });
        $pdo->shouldReceive('prepare')->with('SELECT RELEASE_LOCK(?)')->once()->andReturnUsing(
            function () use (&$releaseCalls, $failRelease) {
                $releaseCalls++;
                $statement = Mockery::mock(PDOStatement::class);
                $statement->shouldReceive('execute')->once()->andReturn(true);
                $statement->shouldReceive('fetchColumn')->once()->andReturn($failRelease ? 0 : 1);

                return $statement;
            },
        );
        $handle = new AdvisoryLockHandle($connection, $pdo, $identity, '701');
        $locks = Mockery::mock(MariaDbBackfillLock::class);
        $locks->shouldReceive('acquire')->once()->andReturn(new LockAcquisition(LockAcquireState::Acquired, $handle));

        return [$locks, function () use (&$releaseCalls): int {
            return $releaseCalls;
        }];
    }
}

final class InMemoryApplyRegistry extends ManagedMediaReferenceRegistry
{
    /** @var list<ManagedMediaReference> */
    public array $rows;

    public ?Closure $afterUpperBound = null;

    /** @param list<ManagedMediaReference> $rows */
    public function __construct(array $rows, private readonly ?int $forcedUpperBound = null)
    {
        $this->rows = $rows;
    }

    public function classification(int $id): PreflightClassification
    {
        foreach ($this->rows as $row) {
            if ($row->id !== $id) {
                continue;
            }
            if ($row->deleted) {
                return PreflightClassification::ExcludedDeleted;
            }
            if ($row->masterKey === null) {
                return PreflightClassification::ExcludedNull;
            }
            if (is_string($row->masterKey) && str_starts_with($row->masterKey, '../')) {
                return PreflightClassification::InvalidReference;
            }
            if (is_string($row->masterKey) && str_ends_with($row->masterKey, '.jpg')) {
                return PreflightClassification::ResponsiveOk;
            }

            return PreflightClassification::LegacyBackfillable;
        }

        return PreflightClassification::ExcludedNull;
    }

    public function upperBound(ManagedMediaDomain $domain): int
    {
        $ids = array_column($this->rows, 'id');
        $upperBound = $this->forcedUpperBound ?? ($ids === [] ? 0 : max($ids));
        if ($this->afterUpperBound !== null) {
            $callback = $this->afterUpperBound;
            $this->afterUpperBound = null;
            $callback($this);
        }

        return $upperBound;
    }

    public function batch(ManagedMediaDomain $domain, int $afterId = 0, int $limit = 100, ?int $throughId = null): array
    {
        $rows = array_values(array_filter(
            $this->rows,
            static fn (ManagedMediaReference $reference): bool => $reference->domain === $domain
                && $reference->id > $afterId && ($throughId === null || $reference->id <= $throughId),
        ));
        usort($rows, static fn (ManagedMediaReference $left, ManagedMediaReference $right): int => $left->id <=> $right->id);

        return array_slice($rows, 0, $limit);
    }

    public function hasReferences(ManagedMediaDomain $domain, int $afterId, int $throughId): bool
    {
        return $this->batch($domain, $afterId, 1, $throughId) !== [];
    }
}

final class CallbackApplyPreflight extends ResponsiveBackfillPreflight
{
    /** @var list<WeakReference> */
    public array $resultReferences = [];

    public function __construct(private readonly Closure $callback) {}

    public function inspect(ManagedMediaReference $reference): PreflightResult
    {
        $result = ($this->callback)($reference);
        $this->resultReferences[] = WeakReference::create($result);

        return $result;
    }
}

final class CallbackApplyItemPublisher extends ApplyItemPublisher
{
    public int $calls = 0;

    /** @var list<int> */
    public array $entityCalls = [];

    public function __construct(private readonly ApplyJournal $journal, private readonly Closure $callback) {}

    public function publish(string $runId, int $itemId, AdvisoryLockHandle $lock): ApplyResult
    {
        $this->calls++;
        $this->entityCalls[] = (int) $this->journal->item($itemId)->entity_id;

        return ($this->callback)($runId, $itemId, $lock);
    }
}

final class SequencedApplyMaintenanceGuard extends ApplyMaintenanceGuard
{
    private int $calls = 0;

    /** @param list<int> $failCalls */
    public function __construct(private readonly array $failCalls) {}

    public function assertAllowed(): void
    {
        $this->calls++;
        if (in_array($this->calls, $this->failCalls, true)) {
            throw new BackfillSafetyException(SafetyError::MaintenanceRequired);
        }
    }
}

final class MutatingMariaDbBackfillLock extends MariaDbBackfillLock
{
    public function __construct(private readonly MariaDbBackfillLock $delegate, private readonly Closure $afterAcquire) {}

    public function acquire(StorageIdentity $identity): LockAcquisition
    {
        $acquisition = $this->delegate->acquire($identity);
        ($this->afterAcquire)();

        return $acquisition;
    }
}

final class ObservingApplyJournal extends ApplyJournal
{
    /** @var list<RecoveryBarrierState> */
    public array $barrierResults = [];

    /** @var list<bool> */
    public array $barrierActiveRuns = [];

    /** @var list<int> */
    public array $checkpointCalls = [];

    public int $heartbeatCalls = 0;

    public bool $failFinishRun = false;

    public bool $failFinishItem = false;

    public int $failBarrierCall = 0;

    public int $finishRunCalls = 0;

    public function __construct(DatabaseManager $database)
    {
        parent::__construct($database);
    }

    public function recoveryBarrier(): RecoveryBarrierState
    {
        $this->barrierActiveRuns[] = DB::table('media_backfill_runs')->where('state', RunState::Active->value)->exists();
        if ($this->failBarrierCall === count($this->barrierActiveRuns)) {
            throw new BackfillSafetyException(SafetyError::JournalUnavailable);
        }
        if ($this->barrierResults !== []) {
            return array_shift($this->barrierResults);
        }

        return parent::recoveryBarrier();
    }

    public function heartbeat(string $runId): void
    {
        $this->heartbeatCalls++;
        parent::heartbeat($runId);
    }

    public function advanceCheckpoint(string $runId, ManagedMediaDomain $domain, int $entityId): void
    {
        parent::advanceCheckpoint($runId, $domain, $entityId);
        $this->checkpointCalls[] = $entityId;
    }

    public function finishItem(int $itemId, ApplyResult $result): void
    {
        if ($this->failFinishItem) {
            throw new BackfillSafetyException(SafetyError::JournalUnavailable);
        }
        parent::finishItem($itemId, $result);
    }

    public function finishRun(string $runId, RunState $state, array $summary = [], ?SafetyError $error = null): void
    {
        $this->finishRunCalls++;
        if ($this->failFinishRun) {
            throw new BackfillSafetyException(SafetyError::JournalUnavailable);
        }
        parent::finishRun($runId, $state, $summary, $error);
    }
}

final class ControllableResponsiveBackfillApply extends ResponsiveBackfillApply
{
    public ?Throwable $finalOwnershipFailure = null;

    public ?StorageIdentity $finalIdentity = null;

    public int $finalOwnershipCalls = 0;

    public int $releaseCalls = 0;

    private int $identityCalls = 0;

    protected function assertFinalLockOwned(AdvisoryLockHandle $lock): void
    {
        $this->finalOwnershipCalls++;
        if ($this->finalOwnershipFailure !== null) {
            throw $this->finalOwnershipFailure;
        }

        parent::assertFinalLockOwned($lock);
    }

    protected function currentIdentity(): StorageIdentity
    {
        $this->identityCalls++;
        if ($this->identityCalls === 4 && $this->finalIdentity !== null) {
            return $this->finalIdentity;
        }

        return parent::currentIdentity();
    }

    protected function releaseLock(AdvisoryLockHandle $lock): void
    {
        $this->releaseCalls++;
        parent::releaseLock($lock);
    }
}
