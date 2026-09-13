<?php

namespace Tests\Feature;

use App\Models\NewsArticle;
use App\Services\Media\Backfill\Reconciliation\ItemReconciliationResult;
use App\Services\Media\Backfill\Reconciliation\ManagedMediaWriterFreezeGuard;
use App\Services\Media\Backfill\Reconciliation\OperationalEvidenceException;
use App\Services\Media\Backfill\Reconciliation\ReconciliationBackendMode;
use App\Services\Media\Backfill\Reconciliation\ReconciliationBlockReason;
use App\Services\Media\Backfill\Reconciliation\ReconciliationCoordinator;
use App\Services\Media\Backfill\Reconciliation\ReconciliationError;
use App\Services\Media\Backfill\Reconciliation\ReconciliationEventType;
use App\Services\Media\Backfill\Reconciliation\ReconciliationException;
use App\Services\Media\Backfill\Reconciliation\ReconciliationInvocation;
use App\Services\Media\Backfill\Reconciliation\ReconciliationJournal;
use App\Services\Media\Backfill\Reconciliation\ReconciliationOutcome;
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
use App\Services\Media\Backfill\Safety\RecoveryBarrierState;
use App\Services\Media\Backfill\Safety\RunState;
use App\Services\Media\Backfill\Safety\SafetyError;
use GuzzleHttp\Promise\Create;
use GuzzleHttp\Psr7\Response;
use Illuminate\Database\Connection;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Filesystem\FilesystemManager;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use League\Flysystem\Local\LocalFilesystemAdapter;
use Mockery;
use PDO;
use PDOStatement;
use PHPUnit\Framework\Attributes\DataProvider;
use Psr\Http\Message\RequestInterface;
use ReflectionClass;
use Tests\Concerns\BackfillSafetyFixtures;
use Tests\TestCase;

class BackfillReconciliationCoordinatorTest extends TestCase
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

    public function test_invalid_noncanonical_run_id_is_rejected_before_any_mutation(): void
    {
        try {
            new ReconciliationInvocation('550E8400-E29B-41D4-A716-446655440000');
            $this->fail('Expected a typed invalid-input failure.');
        } catch (ReconciliationException $error) {
            $this->assertSame(ReconciliationError::InvalidInput, $error->reason);
        }

        $this->assertSame(0, DB::table('media_backfill_reconciliation_events')->count());
    }

    public function test_maintenance_is_required_before_lock_or_attempt_mutation(): void
    {
        [$journal, $runId] = $this->candidatePlan();
        $maintenance = Mockery::mock(ApplyMaintenanceGuard::class);
        $maintenance->shouldReceive('assertAllowed')->once()
            ->andThrow(new BackfillSafetyException(SafetyError::MaintenanceRequired));
        app()->instance(ApplyMaintenanceGuard::class, $maintenance);

        $report = app(ReconciliationCoordinator::class)->run(new ReconciliationInvocation($runId));

        $this->assertSame(ReconciliationOutcome::MaintenanceRequired, $report->outcome);
        $this->assertSame(0, DB::table('media_backfill_reconciliation_events')->count());
        $this->assertNull(DB::table('media_backfill_items')->value('reconciliation_result'));
        $this->assertSame($runId, $journal->run($runId)->run_id);
    }

    public function test_busy_and_failed_lock_acquisition_create_no_attempt(): void
    {
        [, $runId] = $this->candidatePlan();
        $this->backfillLock();

        $busy = app(ReconciliationCoordinator::class)->run(new ReconciliationInvocation($runId));
        $this->assertSame(ReconciliationOutcome::LockBusy, $busy->outcome);
        $this->assertSame(0, DB::table('media_backfill_reconciliation_events')->count());

        $this->releaseBackfillLock();
        $locks = Mockery::mock(MariaDbBackfillLock::class);
        $locks->shouldReceive('acquire')->once()->andReturn(new LockAcquisition(LockAcquireState::Failed));
        app()->instance(MariaDbBackfillLock::class, $locks);

        $failed = app(ReconciliationCoordinator::class)->run(new ReconciliationInvocation($runId));
        $this->assertSame(ReconciliationOutcome::LockAcquireFailed, $failed->outcome);
        $this->assertSame(0, DB::table('media_backfill_reconciliation_events')->count());
    }

    public function test_missing_identity_mismatch_and_malformed_run_fail_before_attempt(): void
    {
        $missing = app(ReconciliationCoordinator::class)->run(
            new ReconciliationInvocation('550e8400-e29b-41d4-a716-446655449999'),
        );
        $this->assertSame(ReconciliationOutcome::SafetyFailure, $missing->outcome);
        $this->assertSame(0, DB::table('media_backfill_reconciliation_events')->count());

        [, $runId] = $this->candidatePlan();
        $otherRoot = $this->safetyRoot.'/other';
        mkdir($otherRoot, 0700);
        config()->set('filesystems.disks.media_local.root', $otherRoot);
        Storage::forgetDisk('media_local');
        $identityMismatch = app(ReconciliationCoordinator::class)->run(new ReconciliationInvocation($runId));
        $this->assertSame(ReconciliationOutcome::SafetyFailure, $identityMismatch->outcome);
        $this->assertSame(0, DB::table('media_backfill_reconciliation_events')->count());

        config()->set('filesystems.disks.media_local.root', $this->safetyRoot);
        Storage::forgetDisk('media_local');
        DB::table('media_backfill_runs')->where('run_id', $runId)->update(['options_json' => '{}']);
        $malformed = app(ReconciliationCoordinator::class)->run(new ReconciliationInvocation($runId));
        $this->assertSame(ReconciliationOutcome::SafetyFailure, $malformed->outcome);
        $this->assertSame(ReconciliationBlockReason::InconsistentJournal, $malformed->firstBlocker);
        $this->assertSame(0, DB::table('media_backfill_reconciliation_events')->count());
    }

    public function test_stale_runtime_adapter_and_more_than_one_thousand_items_fail_closed(): void
    {
        [, $runId] = $this->candidatePlan();
        $otherRoot = $this->safetyRoot.'/runtime-other';
        mkdir($otherRoot, 0700);
        config()->set('filesystems.disks.media_local.root', $otherRoot);
        // Keep the already resolved adapter cached against the old root.
        $runtimeMismatch = app(ReconciliationCoordinator::class)->run(new ReconciliationInvocation($runId));
        $this->assertSame(ReconciliationOutcome::SafetyFailure, $runtimeMismatch->outcome);
        $this->assertSame(0, DB::table('media_backfill_reconciliation_events')->count());

        config()->set('filesystems.disks.media_local.root', $this->safetyRoot);
        Storage::forgetDisk('media_local');
        DB::table('media_backfill_objects')->whereIn(
            'item_id',
            DB::table('media_backfill_items')->where('run_id', $runId)->pluck('id'),
        )->delete();
        DB::table('media_backfill_items')->where('run_id', $runId)->delete();
        $rows = [];
        for ($entityId = 1; $entityId <= 1001; $entityId++) {
            $rows[] = [
                'run_id' => $runId,
                'domain' => 'news',
                'entity_id' => $entityId,
                'master_key' => null,
                'master_key_hash' => hash('sha256', 'null'),
                'manifest_key' => null,
                'preflight_classification' => 'excluded_null',
                'reason_codes_json' => '[]',
                'phase' => 'inspected',
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }
        foreach (array_chunk($rows, 250) as $chunk) {
            DB::table('media_backfill_items')->insert($chunk);
        }

        $oversized = app(ReconciliationCoordinator::class)->run(new ReconciliationInvocation($runId));
        $this->assertSame(ReconciliationOutcome::SafetyFailure, $oversized->outcome);
        $this->assertSame(0, DB::table('media_backfill_reconciliation_events')->count());
    }

    public function test_valid_existing_b3_closure_is_a_no_op_without_a_new_event(): void
    {
        $apply = app(ApplyJournal::class);
        $runId = $apply->createApplyRun($this->identity(), $this->applySelection());
        $context = $this->reconciliationContext();
        $journal = app(ReconciliationJournal::class);
        $journal->beginRunReconciliation(
            $runId,
            $this->reconciliationUuid(1),
            $this->reconciliationMoment(),
            $context,
        );
        $journal->closeRunAfterReconciliation(
            $runId,
            $this->reconciliationUuid(2),
            $this->reconciliationMoment('10:00:01'),
            $context,
        );
        $this->releaseBackfillLock();
        $before = DB::table('media_backfill_reconciliation_events')->count();

        $report = app(ReconciliationCoordinator::class)->run(new ReconciliationInvocation($runId));

        $this->assertSame(ReconciliationOutcome::AlreadyClosed, $report->outcome);
        $this->assertTrue($report->noOp);
        $this->assertNull($report->attemptId);
        $this->assertFalse($report->durableProgressOccurred);
        $this->assertSame($before, DB::table('media_backfill_reconciliation_events')->count());
    }

    public function test_clean_completed_run_without_b3_closure_is_a_no_op_without_an_attempt(): void
    {
        $apply = app(ApplyJournal::class);
        $runId = $apply->createApplyRun($this->identity(), $this->applySelection());
        $itemId = $apply->snapshot($runId, $this->excludedItem(123));
        $apply->finishItem($itemId, ApplyResult::Skipped);
        $apply->finishRun($runId, RunState::Completed, ['skipped' => 1]);
        $this->assertSame(0, DB::table('media_backfill_reconciliation_events')->where('run_id', $runId)->count());

        $report = app(ReconciliationCoordinator::class)->run(new ReconciliationInvocation($runId));

        $this->assertSame(ReconciliationOutcome::NoReconciliationRequired, $report->outcome);
        $this->assertTrue($report->noOp);
        $this->assertNull($report->attemptId);
        $this->assertFalse($report->durableProgressOccurred);
        $this->assertSame(0, $report->itemsResolvedForward);
        $this->assertSame(0, $report->itemsResolvedNoEffect);
        $this->assertSame(RunState::Completed, $report->finalRunState);
        $this->assertSame(RecoveryBarrierState::Clear, $report->globalRecoveryBarrier);
        $this->assertSame(0, DB::table('media_backfill_reconciliation_events')->where('run_id', $runId)->count());
    }

    public function test_no_effect_uses_only_immutable_db_history_and_closes_an_active_run(): void
    {
        [$apply, $runId, $itemId] = $this->candidatePlan();
        $this->installObservationRejectingLocalDisk();
        $runBefore = (array) $apply->run($runId);
        $itemBefore = (array) $apply->item($itemId);

        $report = app(ReconciliationCoordinator::class)->run(new ReconciliationInvocation($runId));

        $this->assertSame(ReconciliationOutcome::Completed, $report->outcome);
        $this->assertSame(1, $report->itemsTraversed);
        $this->assertSame(1, $report->itemsResolvedNoEffect);
        $this->assertSame(0, $report->itemsResolvedForward);
        $this->assertTrue($report->runClosureAppended);
        $this->assertSame(RunState::Interrupted, $report->finalRunState);
        $this->assertSame(RecoveryBarrierState::Clear, $report->globalRecoveryBarrier);
        $this->assertTrue($report->durableProgressOccurred);
        $this->assertSame(ItemReconciliationResult::ClosedNoEffect->value,
            DB::table('media_backfill_items')->where('id', $itemId)->value('reconciliation_result'));
        foreach ([
            ReconciliationEventType::AttemptStarted,
            ReconciliationEventType::ItemNoEffectClosed,
            ReconciliationEventType::RunClosedAfterReconciliation,
        ] as $type) {
            $this->assertSame(1, DB::table('media_backfill_reconciliation_events')
                ->where('event_type', $type->value)->count());
        }
        $runAfter = (array) $apply->run($runId);
        $itemAfter = (array) $apply->item($itemId);
        foreach (['options_json', 'upper_bounds_json', 'checkpoints_json', 'summary_json', 'error_code', 'started_at'] as $field) {
            $this->assertSame($runBefore[$field], $runAfter[$field], $field);
        }
        foreach (['phase', 'apply_result', 'source_sha256', 'candidate_manifest_json',
            'candidate_manifest_sha256', 'revalidated_at', 'finished_at'] as $field) {
            $this->assertSame($itemBefore[$field], $itemAfter[$field], $field);
        }
    }

    public function test_no_effect_ignores_current_domain_reference_and_master_drift(): void
    {
        [$apply, $runId, $itemId] = $this->candidatePlan();
        $historicalKey = (string) $apply->item($itemId)->master_key;
        $article = NewsArticle::factory()->create([
            'id' => 123,
            'image_key' => $historicalKey,
            'image_width' => 400,
            'image_height' => 200,
        ]);
        $driftedKey = 'news/850e8400-e29b-41d4-a716-446655440000.webp';
        $article->update([
            'image_key' => $driftedKey,
            'image_width' => 17,
            'image_height' => 19,
        ]);
        $this->installObservationRejectingLocalDisk();

        $report = app(ReconciliationCoordinator::class)->run(new ReconciliationInvocation($runId));

        $this->assertSame(ReconciliationOutcome::Completed, $report->outcome);
        $this->assertSame(1, $report->itemsResolvedNoEffect);
        $this->assertSame(ItemReconciliationResult::ClosedNoEffect->value,
            DB::table('media_backfill_items')->where('id', $itemId)->value('reconciliation_result'));
        $this->assertSame($driftedKey, $article->fresh()->image_key);
        $this->assertNotSame($historicalKey, $article->fresh()->image_key);
    }

    public function test_no_effect_revalidates_maintenance_immediately_before_journal_mutation(): void
    {
        [, $runId, $itemId] = $this->candidatePlan();
        $calls = 0;
        $maintenance = Mockery::mock(ApplyMaintenanceGuard::class);
        $maintenance->shouldReceive('assertAllowed')->andReturnUsing(function () use (&$calls): void {
            $calls++;
            if ($calls === 8) {
                throw new BackfillSafetyException(SafetyError::MaintenanceRequired);
            }
        });
        app()->instance(ApplyMaintenanceGuard::class, $maintenance);

        $report = app(ReconciliationCoordinator::class)->run(new ReconciliationInvocation($runId));

        $this->assertSame(ReconciliationOutcome::SafetyFailure, $report->outcome);
        $this->assertNull(DB::table('media_backfill_items')->where('id', $itemId)->value('reconciliation_result'));
        $this->assertSame(1, DB::table('media_backfill_reconciliation_events')
            ->where('event_type', ReconciliationEventType::AttemptStarted->value)->count());
        $this->assertSame(0, DB::table('media_backfill_reconciliation_events')
            ->where('event_type', ReconciliationEventType::ItemNoEffectClosed->value)->count());
    }

    #[DataProvider('eligibleNoEffectHistories')]
    public function test_no_effect_accepts_each_supported_immutable_history(string $history): void
    {
        [$apply, $runId, $itemId, $objects] = $this->candidatePlan();
        if ($history !== 'not_dispatched') {
            $apply->commitIntent($objects['variant']);
            $apply->recordReceipt(
                $objects['variant'],
                new CreateReceipt($history === 'rejected_collision' ? CreateState::Rejected : CreateState::Failed),
            );
        }

        $report = app(ReconciliationCoordinator::class)->run(new ReconciliationInvocation($runId));

        $this->assertSame(ReconciliationOutcome::Completed, $report->outcome);
        $this->assertSame(1, $report->itemsResolvedNoEffect);
        $this->assertSame(ItemReconciliationResult::ClosedNoEffect->value,
            DB::table('media_backfill_items')->where('id', $itemId)->value('reconciliation_result'));
        $this->assertSame(0, DB::table('media_backfill_objects')->where('item_id', $itemId)
            ->whereNotNull('reconciliation_resolution')->count());
    }

    public static function eligibleNoEffectHistories(): array
    {
        return [
            'not dispatched' => ['not_dispatched'],
            'rejected collision' => ['rejected_collision'],
            'failed without write' => ['failed_without_write'],
        ];
    }

    public function test_no_effect_allows_a_valid_zero_object_snapshot(): void
    {
        $apply = app(ApplyJournal::class);
        $runId = $apply->createApplyRun($this->identity(), $this->applySelection());
        $itemId = $apply->snapshot($runId, $this->excludedItem(123));
        $this->installObservationRejectingLocalDisk();

        $report = app(ReconciliationCoordinator::class)->run(new ReconciliationInvocation($runId));

        $this->assertSame(ReconciliationOutcome::Completed, $report->outcome);
        $this->assertSame(1, $report->itemsResolvedNoEffect);
        $this->assertSame(ItemReconciliationResult::ClosedNoEffect->value,
            DB::table('media_backfill_items')->where('id', $itemId)->value('reconciliation_result'));
    }

    #[DataProvider('unsafeNoEffectHistories')]
    public function test_intent_unknown_created_and_cleanup_activity_never_use_no_effect(string $history): void
    {
        [$apply, $runId, $itemId, $objects] = $this->candidatePlan();
        $apply->commitIntent($objects['variant']);
        if ($history === 'unknown') {
            $apply->recordReceipt($objects['variant'], new CreateReceipt(CreateState::Unknown));
        } elseif (in_array($history, ['created', 'cleanup_pending'], true)) {
            $apply->recordReceipt($objects['variant'], new CreateReceipt(CreateState::Created, 'opaque'));
            if ($history === 'cleanup_pending') {
                $apply->updateCleanup($objects['variant'], CleanupState::Pending);
            }
        }
        $this->installObservationRejectingLocalDisk();

        $report = app(ReconciliationCoordinator::class)->run(new ReconciliationInvocation($runId));

        $this->assertSame(ReconciliationOutcome::Blocked, $report->outcome);
        $this->assertSame(0, $report->itemsResolvedNoEffect);
        $this->assertNull(DB::table('media_backfill_items')->where('id', $itemId)->value('reconciliation_result'));
        $this->assertSame(0, DB::table('media_backfill_reconciliation_events')
            ->where('event_type', ReconciliationEventType::ItemNoEffectClosed->value)->count());
    }

    public static function unsafeNoEffectHistories(): array
    {
        return [
            'intent' => ['intent'],
            'unknown' => ['unknown'],
            'created receipt and confirmation' => ['created'],
            'cleanup activity' => ['cleanup_pending'],
        ];
    }

    public function test_forward_is_refused_by_non_bypassable_writer_freeze_before_storage_observation_or_resolution(): void
    {
        [$apply, $runId, $itemId, $objects] = $this->candidatePlan();
        $apply->commitIntent($objects['variant']);
        $this->installObservationRejectingLocalDisk();

        $report = app(ReconciliationCoordinator::class)->run(new ReconciliationInvocation($runId));

        $this->assertSame(ReconciliationOutcome::Blocked, $report->outcome);
        $this->assertSame(ReconciliationBlockReason::StorageObservationUntrusted, $report->firstBlocker);
        $this->assertSame(0, $report->itemsResolvedForward);
        $this->assertFalse($report->runClosureAppended);
        $this->assertNull(DB::table('media_backfill_items')->where('id', $itemId)->value('reconciliation_result'));
        $this->assertSame(1, DB::table('media_backfill_reconciliation_events')
            ->where('event_type', ReconciliationEventType::AttemptBlocked->value)->count());
        $this->assertSame(0, DB::table('media_backfill_reconciliation_events')
            ->where('event_type', ReconciliationEventType::ItemForwardAccepted->value)->count());
    }

    public function test_s3_forward_is_refused_before_any_request_or_forward_journal_mutation(): void
    {
        $requests = [];
        $this->installRejectingS3($requests);
        [$apply, $runId, $itemId, $objects] = $this->candidatePlan();
        $apply->commitIntent($objects['variant']);

        $report = app(ReconciliationCoordinator::class)->run(new ReconciliationInvocation($runId));

        $this->assertSame(ReconciliationOutcome::Blocked, $report->outcome);
        $this->assertSame(ReconciliationBlockReason::StorageObservationUntrusted, $report->firstBlocker);
        $this->assertSame([], $requests);
        $this->assertNull(DB::table('media_backfill_items')->where('id', $itemId)->value('reconciliation_result'));
        $this->assertSame(0, DB::table('media_backfill_reconciliation_events')
            ->where('event_type', ReconciliationEventType::ItemForwardAccepted->value)->count());
    }

    public function test_cleanup_attention_is_not_cleared_and_prevents_run_closure(): void
    {
        [$apply, $runId, $itemId, $objects] = $this->candidatePlan();
        $apply->commitIntent($objects['variant']);
        $apply->recordReceipt($objects['variant'], new CreateReceipt(CreateState::Created, 'opaque'));
        $apply->updateCleanup($objects['variant'], CleanupState::Pending);
        $apply->finishItem($itemId, ApplyResult::FailedCleanupIncomplete);

        $report = app(ReconciliationCoordinator::class)->run(new ReconciliationInvocation($runId));

        $this->assertSame(ReconciliationOutcome::Blocked, $report->outcome);
        $this->assertSame(ReconciliationBlockReason::ResolutionConflict, $report->firstBlocker);
        $this->assertFalse($report->runClosureAppended);
        $this->assertSame(CleanupState::Pending->value,
            DB::table('media_backfill_objects')->where('id', $objects['variant'])->value('cleanup_state'));
        $this->assertSame(RunState::Active->value,
            DB::table('media_backfill_runs')->where('run_id', $runId)->value('state'));
    }

    public function test_first_blocker_stops_traversal_after_preserving_prior_item_atomic_progress_and_retry_gets_new_attempt(): void
    {
        $apply = app(ApplyJournal::class);
        $runId = $apply->createApplyRun($this->identity(), $this->applySelection(limit: 2));
        [$firstItem] = $this->candidateItem($apply, $runId, 123);
        [$secondItem, $objects] = $this->candidateItem($apply, $runId, 124);
        $apply->commitIntent($objects['variant']);

        $first = app(ReconciliationCoordinator::class)->run(new ReconciliationInvocation($runId));
        $second = app(ReconciliationCoordinator::class)->run(new ReconciliationInvocation($runId));

        $this->assertSame(ReconciliationOutcome::Blocked, $first->outcome);
        $this->assertSame(2, $first->itemsTraversed);
        $this->assertSame(1, $first->itemsResolvedNoEffect);
        $this->assertSame(ItemReconciliationResult::ClosedNoEffect->value,
            DB::table('media_backfill_items')->where('id', $firstItem)->value('reconciliation_result'));
        $this->assertNull(DB::table('media_backfill_items')->where('id', $secondItem)->value('reconciliation_result'));
        $this->assertSame(ReconciliationOutcome::Blocked, $second->outcome);
        $this->assertNotSame($first->attemptId, $second->attemptId);
        $this->assertSame(2, $second->itemsTraversed);
        $this->assertSame(1, $second->itemsSkipped);
        $this->assertSame(2, DB::table('media_backfill_reconciliation_events')
            ->where('event_type', ReconciliationEventType::AttemptBlocked->value)->count());
    }

    #[DataProvider('terminalStatesWithoutFirstClosure')]
    public function test_terminal_failed_or_interrupted_run_can_resolve_no_effect_but_never_receives_first_closure(
        RunState $terminalState,
    ): void {
        [$apply, $runId, $itemId] = $this->candidatePlan();
        $apply->finishRun($runId, $terminalState);

        $report = app(ReconciliationCoordinator::class)->run(new ReconciliationInvocation($runId));

        $this->assertSame(ReconciliationOutcome::Completed, $report->outcome);
        $this->assertSame($terminalState, $report->finalRunState);
        $this->assertFalse($report->runClosureAppended);
        $this->assertSame(ItemReconciliationResult::ClosedNoEffect->value,
            DB::table('media_backfill_items')->where('id', $itemId)->value('reconciliation_result'));
        $this->assertSame(0, DB::table('media_backfill_reconciliation_events')
            ->where('event_type', ReconciliationEventType::RunClosedAfterReconciliation->value)->count());
    }

    public static function terminalStatesWithoutFirstClosure(): array
    {
        return [
            'failed' => [RunState::Failed],
            'interrupted' => [RunState::Interrupted],
        ];
    }

    #[DataProvider('terminalStatesWithoutFirstClosure')]
    public function test_resolved_terminal_run_retry_is_a_no_op_without_a_new_attempt(RunState $terminalState): void
    {
        [$apply, $runId, $itemId] = $this->candidatePlan();
        $apply->finishRun($runId, $terminalState);
        $context = $this->reconciliationContext();
        $journal = app(ReconciliationJournal::class);
        $journal->beginRunReconciliation(
            $runId,
            $this->reconciliationUuid(1),
            $this->reconciliationMoment(),
            $context,
        );
        $journal->recordNoEffectItemResolution(
            $runId,
            $itemId,
            $this->reconciliationUuid(2),
            $this->reconciliationMoment('10:00:01'),
            $context,
        );
        $this->releaseBackfillLock();
        $before = DB::table('media_backfill_reconciliation_events')->where('run_id', $runId)->count();

        $report = app(ReconciliationCoordinator::class)->run(new ReconciliationInvocation($runId));

        $this->assertSame(ReconciliationOutcome::NoReconciliationRequired, $report->outcome);
        $this->assertTrue($report->noOp);
        $this->assertNull($report->attemptId);
        $this->assertFalse($report->durableProgressOccurred);
        $this->assertSame($terminalState, $report->finalRunState);
        $this->assertSame($before,
            DB::table('media_backfill_reconciliation_events')->where('run_id', $runId)->count());
        $this->assertSame(1, DB::table('media_backfill_reconciliation_events')
            ->where('run_id', $runId)->where('event_type', ReconciliationEventType::AttemptStarted->value)->count());
    }

    public function test_active_resolved_run_still_starts_an_attempt_and_closes_through_b3(): void
    {
        [, $runId, $itemId] = $this->candidatePlan();
        $context = $this->reconciliationContext();
        $journal = app(ReconciliationJournal::class);
        $journal->beginRunReconciliation(
            $runId,
            $this->reconciliationUuid(1),
            $this->reconciliationMoment(),
            $context,
        );
        $journal->recordNoEffectItemResolution(
            $runId,
            $itemId,
            $this->reconciliationUuid(2),
            $this->reconciliationMoment('10:00:01'),
            $context,
        );
        $this->releaseBackfillLock();

        $report = app(ReconciliationCoordinator::class)->run(new ReconciliationInvocation($runId));

        $this->assertSame(ReconciliationOutcome::Completed, $report->outcome);
        $this->assertFalse($report->noOp);
        $this->assertNotNull($report->attemptId);
        $this->assertSame(1, $report->itemsTraversed);
        $this->assertSame(1, $report->itemsSkipped);
        $this->assertTrue($report->runClosureAppended);
        $this->assertTrue($report->durableProgressOccurred);
        $this->assertSame(RunState::Interrupted, $report->finalRunState);
        $this->assertSame(2, DB::table('media_backfill_reconciliation_events')
            ->where('run_id', $runId)->where('event_type', ReconciliationEventType::AttemptStarted->value)->count());
        $this->assertSame(1, DB::table('media_backfill_reconciliation_events')
            ->where('run_id', $runId)
            ->where('event_type', ReconciliationEventType::RunClosedAfterReconciliation->value)->count());
    }

    public function test_other_run_may_keep_global_barrier_blocked_after_selected_run_completes(): void
    {
        $apply = app(ApplyJournal::class);
        $selected = $apply->createApplyRun($this->identity(), $this->applySelection());
        $item = $apply->snapshot($selected, $this->excludedItem(123));
        $apply->finishItem($item, ApplyResult::Skipped);
        $apply->finishRun($selected, RunState::Completed, ['skipped' => 1]);
        $other = $apply->createApplyRun($this->identity(), $this->applySelection());

        $report = app(ReconciliationCoordinator::class)->run(new ReconciliationInvocation($selected));

        $this->assertSame(ReconciliationOutcome::NoReconciliationRequired, $report->outcome);
        $this->assertTrue($report->noOp);
        $this->assertNull($report->attemptId);
        $this->assertFalse($report->durableProgressOccurred);
        $this->assertSame(RunState::Completed, $report->finalRunState);
        $this->assertSame(RecoveryBarrierState::Blocked, $report->globalRecoveryBarrier);
        $this->assertSame(0, DB::table('media_backfill_reconciliation_events')
            ->where('run_id', $selected)->count());
        $this->assertSame(RunState::Active->value, $apply->run($other)->state);
    }

    public function test_malformed_existing_projection_is_never_skipped_as_resolved(): void
    {
        [, $runId, $itemId] = $this->candidatePlan();
        DB::table('media_backfill_items')->where('id', $itemId)->update([
            'reconciliation_result' => ItemReconciliationResult::ClosedNoEffect->value,
            'reconciliation_event_id' => null,
        ]);

        $report = app(ReconciliationCoordinator::class)->run(new ReconciliationInvocation($runId));

        $this->assertSame(ReconciliationOutcome::SafetyFailure, $report->outcome);
        $this->assertSame(ReconciliationBlockReason::InconsistentJournal, $report->firstBlocker);
        $this->assertSame(0, $report->itemsSkipped);
        $this->assertSame(0, DB::table('media_backfill_reconciliation_events')->count());
    }

    public function test_orphan_resolution_event_is_inconsistent_and_cannot_be_repaired_by_a_retry(): void
    {
        [, $runId, $itemId] = $this->candidatePlan();
        $context = $this->reconciliationContext();
        $journal = app(ReconciliationJournal::class);
        $journal->beginRunReconciliation(
            $runId,
            $this->reconciliationUuid(1),
            $this->reconciliationMoment(),
            $context,
        );
        $journal->recordNoEffectItemResolution(
            $runId,
            $itemId,
            $this->reconciliationUuid(2),
            $this->reconciliationMoment('10:00:01'),
            $context,
        );
        DB::table('media_backfill_items')->where('id', $itemId)->update([
            'reconciliation_result' => null,
            'reconciliation_event_id' => null,
        ]);
        $this->releaseBackfillLock();
        $before = DB::table('media_backfill_reconciliation_events')->count();

        $report = app(ReconciliationCoordinator::class)->run(new ReconciliationInvocation($runId));

        $this->assertSame(ReconciliationOutcome::SafetyFailure, $report->outcome);
        $this->assertSame(ReconciliationBlockReason::InconsistentJournal, $report->firstBlocker);
        $this->assertSame($before, DB::table('media_backfill_reconciliation_events')->count());
        $this->assertNull(DB::table('media_backfill_items')->where('id', $itemId)->value('reconciliation_result'));
    }

    public function test_lock_loss_at_attempt_commit_fails_closed_without_claiming_progress(): void
    {
        [, $runId] = $this->candidatePlan();
        $lock = $this->backfillLock();
        $locks = Mockery::mock(MariaDbBackfillLock::class);
        $locks->shouldReceive('acquire')->once()->andReturn(new LockAcquisition(LockAcquireState::Acquired, $lock));
        app()->instance(MariaDbBackfillLock::class, $locks);
        $fired = false;
        $connectionId = (int) $lock->connectionId;
        DB::listen(function (QueryExecuted $query) use (&$fired, $connectionId): void {
            if (! $fired && str_contains($query->sql, 'insert into `media_backfill_reconciliation_events`')) {
                $fired = true;
                DB::unprepared('KILL CONNECTION '.$connectionId);
            }
        });

        $report = app(ReconciliationCoordinator::class)->run(new ReconciliationInvocation($runId));

        $this->assertTrue($fired);
        $this->assertSame(ReconciliationOutcome::SafetyFailure, $report->outcome);
        $this->assertNull($report->durableProgressOccurred);
        $this->assertSame(0, DB::table('media_backfill_reconciliation_events')->count());
    }

    public function test_uncertain_lock_release_after_durable_progress_cannot_report_clean_success(): void
    {
        [, $runId] = $this->candidatePlan();
        [$locks, $releaseCalls] = $this->lockManagerWithObservedReleaseFailure();
        app()->instance(MariaDbBackfillLock::class, $locks);

        $report = app(ReconciliationCoordinator::class)->run(new ReconciliationInvocation($runId));

        $this->assertSame(ReconciliationOutcome::SafetyFailure, $report->outcome);
        $this->assertNull($report->durableProgressOccurred);
        $this->assertTrue($report->runClosureAppended);
        $this->assertSame(RunState::Interrupted, $report->finalRunState);
        $this->assertSame(1, $releaseCalls());
    }

    public function test_c3_command_is_the_only_operational_coordinator_caller_and_journal_api_remains_five_methods(): void
    {
        $methods = array_map(
            static fn (\ReflectionMethod $method): string => $method->getName(),
            (new ReflectionClass(ReconciliationJournal::class))->getMethods(\ReflectionMethod::IS_PUBLIC),
        );
        sort($methods);
        $this->assertSame([
            'beginRunReconciliation',
            'closeRunAfterReconciliation',
            'recordBlockedAttempt',
            'recordForwardItemResolution',
            'recordNoEffectItemResolution',
        ], array_values(array_filter($methods, static fn (string $method): bool => $method !== '__construct')));

        $callers = [];
        foreach ([app_path('Console'), base_path('routes'), app_path('Http'), app_path('Jobs')] as $path) {
            if (! is_dir($path)) {
                continue;
            }
            foreach (File::allFiles($path) as $file) {
                if (str_contains((string) File::get($file->getPathname()), 'ReconciliationCoordinator')) {
                    $callers[] = $file->getPathname();
                }
            }
        }
        $this->assertSame([
            app_path('Console/Commands/ResponsiveBackfillReconcileRunCommand.php'),
        ], $callers);
        $guard = new ManagedMediaWriterFreezeGuard;
        try {
            $guard->assertEstablished(ReconciliationBackendMode::S3);
            $this->fail('Forward writer freeze must remain unavailable.');
        } catch (OperationalEvidenceException $error) {
            $this->assertSame(ReconciliationBlockReason::StorageObservationUntrusted, $error->reason);
        }
    }

    private function installObservationRejectingLocalDisk(): void
    {
        $config = config('filesystems.disks.media_local');
        $disk = Mockery::mock(FilesystemAdapter::class);
        $disk->shouldReceive('getConfig')->andReturn($config);
        $disk->shouldReceive('getAdapter')->andReturn(new LocalFilesystemAdapter($this->safetyRoot));
        $disk->shouldReceive('path')->andReturnUsing(fn (string $key): string => $this->safetyRoot.'/'.$key);
        $disk->shouldNotReceive('fileExists');
        $disk->shouldNotReceive('exists');
        $disk->shouldNotReceive('size');
        $disk->shouldNotReceive('readStream');
        $disk->shouldNotReceive('put');
        $disk->shouldNotReceive('delete');
        $disk->shouldNotReceive('copy');
        $disk->shouldNotReceive('move');
        $disk->shouldNotReceive('files');
        $disk->shouldNotReceive('allFiles');
        $filesystems = Mockery::mock(FilesystemManager::class);
        $filesystems->shouldReceive('disk')->with('media_local')->andReturn($disk);
        app()->instance(FilesystemManager::class, $filesystems);
    }

    /** @param list<RequestInterface> $requests */
    private function installRejectingS3(array &$requests): void
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
            'http_handler' => function (RequestInterface $request) use (&$requests) {
                $requests[] = $request;

                return Create::rejectionFor([
                    'exception' => new \RuntimeException('no S3 request is allowed'),
                    'response' => new Response(500),
                ]);
            },
        ];
        config()->set('media.disk', 'media_s3');
        config()->set('filesystems.disks.media_s3', $config);
        Storage::set('media_s3', Storage::build($config));
    }

    /** @return array{MariaDbBackfillLock, callable(): int} */
    private function lockManagerWithObservedReleaseFailure(): array
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
            function () use (&$releaseCalls) {
                $releaseCalls++;
                $statement = Mockery::mock(PDOStatement::class);
                $statement->shouldReceive('execute')->once()->andReturn(true);
                $statement->shouldReceive('fetchColumn')->once()->andReturn(0);

                return $statement;
            },
        );
        $handle = new AdvisoryLockHandle($connection, $pdo, $identity, '701');
        $locks = Mockery::mock(MariaDbBackfillLock::class);
        $locks->shouldReceive('acquire')->once()
            ->andReturn(new LockAcquisition(LockAcquireState::Acquired, $handle));

        return [$locks, function () use (&$releaseCalls): int {
            return $releaseCalls;
        }];
    }
}
