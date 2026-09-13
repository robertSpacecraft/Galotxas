<?php

namespace Tests\Feature;

use App\Console\Commands\ResponsiveBackfillReconcileRunCommand;
use App\Services\Media\Backfill\Reconciliation\ItemReconciliationResult;
use App\Services\Media\Backfill\Reconciliation\ReconciliationBlockReason;
use App\Services\Media\Backfill\Reconciliation\ReconciliationCoordinator;
use App\Services\Media\Backfill\Reconciliation\ReconciliationEventType;
use App\Services\Media\Backfill\Reconciliation\ReconciliationInvocation;
use App\Services\Media\Backfill\Reconciliation\ReconciliationJournal;
use App\Services\Media\Backfill\Reconciliation\ReconciliationOutcome;
use App\Services\Media\Backfill\Reconciliation\ReconciliationReport;
use App\Services\Media\Backfill\Safety\AdvisoryLockHandle;
use App\Services\Media\Backfill\Safety\ApplyJournal;
use App\Services\Media\Backfill\Safety\ApplyMaintenanceGuard;
use App\Services\Media\Backfill\Safety\ApplyResult;
use App\Services\Media\Backfill\Safety\BackfillSafetyException;
use App\Services\Media\Backfill\Safety\LockAcquireState;
use App\Services\Media\Backfill\Safety\LockAcquisition;
use App\Services\Media\Backfill\Safety\MariaDbBackfillLock;
use App\Services\Media\Backfill\Safety\RecoveryBarrierState;
use App\Services\Media\Backfill\Safety\RunState;
use App\Services\Media\Backfill\Safety\SafetyError;
use Illuminate\Contracts\Console\Kernel as ConsoleKernel;
use Illuminate\Database\Connection;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Filesystem\FilesystemManager;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use League\Flysystem\Local\LocalFilesystemAdapter;
use Mockery;
use PDO;
use PDOStatement;
use PHPUnit\Framework\Attributes\DataProvider;
use ReflectionClass;
use ReflectionMethod;
use RuntimeException;
use Symfony\Component\Console\Input\StringInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Tests\Concerns\BackfillSafetyFixtures;
use Tests\TestCase;

class ResponsiveBackfillReconcileRunCommandTest extends TestCase
{
    use BackfillSafetyFixtures;
    use DatabaseTruncation;

    private const RUN_UUID = '550e8400-e29b-41d4-a716-446655440000';

    private const ATTEMPT_UUID = '550e8400-e29b-41d4-a716-446655440001';

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

    public function test_command_is_registered_with_the_exact_c3_option_surface(): void
    {
        $this->assertArrayHasKey('media:responsive-backfill-reconcile-run', Artisan::all());

        $command = $this->app->make(ResponsiveBackfillReconcileRunCommand::class);
        $this->assertTrue($command->getDefinition()->hasOption('run'));
        $this->assertTrue($command->getDefinition()->hasOption('execute'));
        foreach (['item', 'object', 'after-id', 'limit', 'force', 'resume', 'cleanup', 'finalize', 'apply', 'dry-run', 'yes'] as $option) {
            $this->assertFalse($command->getDefinition()->hasOption($option), $option);
        }

        [$exitCode, $help] = $this->callRaw('--help');
        $this->assertSame(0, $exitCode);
        $this->assertStringContainsString('--run', $help);
        $this->assertStringContainsString('--execute', $help);
        $this->assertStringContainsString('no omite ningún gate de seguridad', $help);
        $this->assertStringNotContainsString('--limit', $help);
        $this->assertStringNotContainsString('--force', $help);
    }

    #[DataProvider('invalidInvocations')]
    public function test_invalid_cli_returns_two_without_invoking_the_coordinator(
        string $arguments,
        ?string $expectedOutput = null,
        ?string $sensitiveValue = null,
    ): void {
        $coordinator = Mockery::mock(ReconciliationCoordinator::class);
        $coordinator->shouldNotReceive('run');
        $this->app->instance(ReconciliationCoordinator::class, $coordinator);

        [$exitCode, $output] = $this->callRaw($arguments);

        $this->assertSame(2, $exitCode, $arguments.' '.$output);
        if ($expectedOutput !== null) {
            $this->assertStringContainsString($expectedOutput, $output);
        }
        if ($sensitiveValue !== null) {
            $this->assertStringNotContainsString($sensitiveValue, $output);
        }
        $this->assertStringNotContainsString('Modo: RECONCILIATION MUTATING', $output);
        $this->assertSame(0, DB::table('media_backfill_reconciliation_events')->count());
    }

    public static function invalidInvocations(): iterable
    {
        yield 'no options' => ['', 'media:responsive-backfill-reconcile --run=<uuid>'];
        yield 'run without execute' => ['--run='.self::RUN_UUID, 'media:responsive-backfill-reconcile --run=<uuid>'];
        yield 'execute without run' => ['--execute', 'UUID canónico en minúsculas'];
        yield 'uppercase UUID' => ['--run=550E8400-E29B-41D4-A716-446655440000 --execute', 'UUID canónico en minúsculas'];
        yield 'malformed UUID' => ['--run=not-a-uuid --execute', 'UUID canónico en minúsculas'];
        yield 'braced UUID' => ['--run={550e8400-e29b-41d4-a716-446655440000} --execute', 'UUID canónico en minúsculas'];
        yield 'UUID with suffix' => ['--run='.self::RUN_UUID.'x --execute', 'UUID canónico en minúsculas'];
        yield 'empty run value' => ['--run= --execute', 'UUID canónico en minúsculas'];
        yield 'run option without value' => ['--run --execute', 'UUID canónico en minúsculas'];
        yield 'execute with joined value' => ['--run='.self::RUN_UUID.' --execute=private-flag-value', 'Opciones no válidas', 'private-flag-value'];
        yield 'item unsupported' => ['--run='.self::RUN_UUID.' --execute --item=private-item-value', 'Opciones no válidas', 'private-item-value'];
        yield 'object unsupported' => ['--run='.self::RUN_UUID.' --execute --object=private-object-value', 'Opciones no válidas', 'private-object-value'];
        yield 'limit unsupported' => ['--run='.self::RUN_UUID.' --execute --limit=999', 'Opciones no válidas', '999'];
        yield 'force unsupported' => ['--run='.self::RUN_UUID.' --execute --force', 'Opciones no válidas'];
        yield 'resume unsupported' => ['--run='.self::RUN_UUID.' --execute --resume', 'Opciones no válidas'];
        yield 'cleanup unsupported' => ['--run='.self::RUN_UUID.' --execute --cleanup', 'Opciones no válidas'];
        yield 'finalize unsupported' => ['--run='.self::RUN_UUID.' --execute --finalize', 'Opciones no válidas'];
        yield 'apply unsupported' => ['--run='.self::RUN_UUID.' --execute --apply', 'Opciones no válidas'];
        yield 'dry-run unsupported' => ['--run='.self::RUN_UUID.' --execute --dry-run', 'Opciones no válidas'];
        yield 'yes unsupported' => ['--run='.self::RUN_UUID.' --execute --yes', 'Opciones no válidas'];
    }

    public function test_valid_cli_constructs_one_exact_invocation_and_prints_mutating_mode_before_call(): void
    {
        $output = new BufferedOutput;
        $headerBeforeCoordinator = null;
        $coordinator = Mockery::mock(ReconciliationCoordinator::class);
        $coordinator->shouldReceive('run')->once()
            ->with(Mockery::on(static fn (ReconciliationInvocation $invocation): bool => $invocation->runId === self::RUN_UUID))
            ->andReturnUsing(function () use ($output, &$headerBeforeCoordinator): ReconciliationReport {
                $headerBeforeCoordinator = $output->fetch();

                return $this->report(ReconciliationOutcome::Completed, self::ATTEMPT_UUID, true);
            });
        $this->app->instance(ReconciliationCoordinator::class, $coordinator);

        $exitCode = Artisan::call('media:responsive-backfill-reconcile-run', [
            '--run' => self::RUN_UUID,
            '--execute' => true,
        ], $output);

        $this->assertSame(0, $exitCode);
        $this->assertStringContainsString('Modo: RECONCILIATION MUTATING / EXACT RUN', $headerBeforeCoordinator);
        $this->assertStringNotContainsString('forward disponible', strtolower((string) $headerBeforeCoordinator));
        $this->assertStringContainsString('Process exit: 0', $output->fetch());
    }

    #[DataProvider('typedOutcomeExitCases')]
    public function test_typed_report_outcomes_map_to_exact_exit_codes(
        ReconciliationOutcome $outcome,
        int $expectedExit,
        ?string $attemptId,
        ?bool $durableProgress,
    ): void {
        $this->bindReport($this->report($outcome, $attemptId, $durableProgress));

        $exitCode = Artisan::call('media:responsive-backfill-reconcile-run', [
            '--run' => self::RUN_UUID,
            '--execute' => true,
        ]);
        $output = Artisan::output();

        $this->assertSame($expectedExit, $exitCode);
        $this->assertStringContainsString('Outcome: '.$outcome->value, $output);
        $this->assertStringContainsString('Process exit: '.$expectedExit, $output);
    }

    public static function typedOutcomeExitCases(): iterable
    {
        yield 'completed' => [ReconciliationOutcome::Completed, 0, self::ATTEMPT_UUID, true];
        yield 'already closed' => [ReconciliationOutcome::AlreadyClosed, 0, null, false];
        yield 'no reconciliation required' => [ReconciliationOutcome::NoReconciliationRequired, 0, null, false];
        yield 'maintenance required' => [ReconciliationOutcome::MaintenanceRequired, 3, null, false];
        yield 'lock busy' => [ReconciliationOutcome::LockBusy, 4, null, false];
        yield 'lock acquisition failed' => [ReconciliationOutcome::LockAcquireFailed, 5, null, false];
        yield 'blocked' => [ReconciliationOutcome::Blocked, 7, self::ATTEMPT_UUID, true];
    }

    #[DataProvider('safetyFailureExitCases')]
    public function test_safety_failure_uses_attempt_and_durable_progress_facts_for_exit_precedence(
        ?string $attemptId,
        ?bool $durableProgress,
        int $expectedExit,
    ): void {
        $this->bindReport($this->report(
            ReconciliationOutcome::SafetyFailure,
            $attemptId,
            $durableProgress,
        ));

        $exitCode = Artisan::call('media:responsive-backfill-reconcile-run', [
            '--run' => self::RUN_UUID,
            '--execute' => true,
        ]);

        $this->assertSame($expectedExit, $exitCode);
        $this->assertStringContainsString('Durable progress: '.($durableProgress === null
            ? 'unknown'
            : ($durableProgress ? 'yes' : 'no')), Artisan::output());
    }

    public static function safetyFailureExitCases(): iterable
    {
        yield 'clean pre-attempt refusal' => [null, false, 6];
        yield 'clean no-attempt release uncertainty' => [null, null, 6];
        yield 'attempt identity wins' => [self::ATTEMPT_UUID, false, 7];
        yield 'durable progress wins' => [null, true, 7];
        yield 'attempt with uncertain progress wins' => [self::ATTEMPT_UUID, null, 7];
    }

    public function test_blocked_global_barrier_does_not_override_selected_run_success(): void
    {
        $this->bindReport($this->report(
            ReconciliationOutcome::NoReconciliationRequired,
            barrier: RecoveryBarrierState::Blocked,
        ));

        $exitCode = Artisan::call('media:responsive-backfill-reconcile-run', [
            '--run' => self::RUN_UUID,
            '--execute' => true,
        ]);
        $output = Artisan::output();

        $this->assertSame(0, $exitCode);
        $this->assertStringContainsString('Global recovery barrier: blocked', $output);
        $this->assertStringContainsString('Process exit: 0', $output);
    }

    public function test_report_output_is_bounded_and_an_unexpected_exception_is_sanitized(): void
    {
        $coordinator = Mockery::mock(ReconciliationCoordinator::class);
        $coordinator->shouldReceive('run')->once()
            ->andThrow(new RuntimeException('private/object/key.webp credential=private ETag=secret VersionId=secret'));
        $this->app->instance(ReconciliationCoordinator::class, $coordinator);

        $exitCode = Artisan::call('media:responsive-backfill-reconcile-run', [
            '--run' => self::RUN_UUID,
            '--execute' => true,
        ]);
        $output = Artisan::output();

        $this->assertSame(7, $exitCode);
        $this->assertStringContainsString('falló de forma no clasificable', $output);
        $this->assertStringContainsString('Process exit: 7', $output);
        foreach (['private/object/key.webp', 'credential=private', 'ETag=secret', 'VersionId=secret', 'Stack trace'] as $secret) {
            $this->assertStringNotContainsString($secret, $output);
        }
    }

    public function test_real_coordinator_maintenance_refusal_maps_three_without_attempt_or_projection(): void
    {
        [, $runId, $itemId] = $this->candidatePlan();
        $maintenance = Mockery::mock(ApplyMaintenanceGuard::class);
        $maintenance->shouldReceive('assertAllowed')->once()
            ->andThrow(new BackfillSafetyException(SafetyError::MaintenanceRequired));
        $this->app->instance(ApplyMaintenanceGuard::class, $maintenance);

        $exitCode = $this->callValid($runId);

        $this->assertSame(3, $exitCode);
        $this->assertStringContainsString('Se requiere mantenimiento de Laravel', Artisan::output());
        $this->assertSame(0, DB::table('media_backfill_reconciliation_events')->count());
        $this->assertNull(DB::table('media_backfill_items')->where('id', $itemId)->value('reconciliation_result'));
        $this->assertNull(DB::table('media_backfill_runs')->where('run_id', $runId)->value('reconciliation_event_id'));
    }

    public function test_real_coordinator_no_effect_path_is_db_only_and_preserves_apply_facts(): void
    {
        [$journal, $runId, $itemId] = $this->candidatePlan();
        $before = $this->immutableApplyFacts($runId);
        $this->installObservationRejectingLocalDisk();

        $exitCode = $this->callValid($runId);
        $output = Artisan::output();

        $this->assertSame(0, $exitCode);
        $this->assertStringContainsString('Outcome: completed', $output);
        $this->assertStringContainsString('Items resolved no-effect: 1', $output);
        $this->assertStringContainsString('Items resolved forward: 0', $output);
        $this->assertStringContainsString('Global recovery barrier: clear', $output);
        $this->assertSame(ItemReconciliationResult::ClosedNoEffect->value,
            DB::table('media_backfill_items')->where('id', $itemId)->value('reconciliation_result'));
        $this->assertSame(1, DB::table('media_backfill_reconciliation_events')
            ->where('event_type', ReconciliationEventType::AttemptStarted->value)->count());
        $this->assertSame(1, DB::table('media_backfill_reconciliation_events')
            ->where('event_type', ReconciliationEventType::ItemNoEffectClosed->value)->count());
        $this->assertSame(1, DB::table('media_backfill_reconciliation_events')
            ->where('event_type', ReconciliationEventType::RunClosedAfterReconciliation->value)->count());
        $this->assertSame($before, $this->immutableApplyFacts($runId));
        $this->assertSame(RunState::Interrupted->value, $journal->run($runId)->state);
    }

    public function test_real_coordinator_clean_terminal_run_is_no_reconciliation_required_without_attempt(): void
    {
        $journal = app(ApplyJournal::class);
        $runId = $journal->createApplyRun($this->identity(), $this->applySelection());
        $itemId = $journal->snapshot($runId, $this->excludedItem(123));
        $journal->finishItem($itemId, ApplyResult::Skipped);
        $journal->finishRun($runId, RunState::Completed, ['skipped' => 1]);

        $exitCode = $this->callValid($runId);
        $output = Artisan::output();

        $this->assertSame(0, $exitCode);
        $this->assertStringContainsString('Outcome: no_reconciliation_required', $output);
        $this->assertStringContainsString('No-op: yes', $output);
        $this->assertStringContainsString('Attempt: -', $output);
        $this->assertSame(0, DB::table('media_backfill_reconciliation_events')->where('run_id', $runId)->count());
    }

    public function test_real_coordinator_valid_b3_closure_is_already_closed_without_new_event(): void
    {
        $journal = app(ApplyJournal::class);
        $runId = $journal->createApplyRun($this->identity(), $this->applySelection());
        $context = $this->reconciliationContext();
        $reconciliation = app(ReconciliationJournal::class);
        $reconciliation->beginRunReconciliation(
            $runId,
            $this->reconciliationUuid(1),
            $this->reconciliationMoment(),
            $context,
        );
        $reconciliation->closeRunAfterReconciliation(
            $runId,
            $this->reconciliationUuid(2),
            $this->reconciliationMoment('10:00:01'),
            $context,
        );
        $this->releaseBackfillLock();
        $before = DB::table('media_backfill_reconciliation_events')->where('run_id', $runId)->count();

        $exitCode = $this->callValid($runId);
        $output = Artisan::output();

        $this->assertSame(0, $exitCode);
        $this->assertStringContainsString('Outcome: already_closed', $output);
        $this->assertStringContainsString('No-op: yes', $output);
        $this->assertSame($before, DB::table('media_backfill_reconciliation_events')->where('run_id', $runId)->count());
    }

    public function test_real_coordinator_forward_candidate_remains_fail_closed_and_maps_seven(): void
    {
        [$journal, $runId, $itemId, $objects] = $this->candidatePlan();
        $journal->commitIntent($objects['variant']);
        $this->installObservationRejectingLocalDisk();

        $exitCode = $this->callValid($runId);
        $output = Artisan::output();

        $this->assertSame(7, $exitCode);
        $this->assertStringContainsString('Outcome: blocked', $output);
        $this->assertStringContainsString('First blocker: storage_observation_untrusted', $output);
        $this->assertStringContainsString('El run seleccionado quedó bloqueado de forma fail-closed.', $output);
        $this->assertStringContainsString('Items resolved forward: 0', $output);
        $this->assertStringContainsString('Process exit: 7', $output);
        $this->assertNull(DB::table('media_backfill_items')->where('id', $itemId)->value('reconciliation_result'));
        $this->assertSame(1, DB::table('media_backfill_reconciliation_events')
            ->where('event_type', ReconciliationEventType::AttemptStarted->value)->count());
        $this->assertSame(1, DB::table('media_backfill_reconciliation_events')
            ->where('event_type', ReconciliationEventType::AttemptBlocked->value)->count());
        $this->assertSame(0, DB::table('media_backfill_reconciliation_events')
            ->where('event_type', ReconciliationEventType::ItemForwardAccepted->value)->count());
    }

    public function test_real_coordinator_lock_busy_maps_four_without_attempt(): void
    {
        [, $runId] = $this->candidatePlan();
        $this->backfillLock();

        $exitCode = $this->callValid($runId);

        $this->assertSame(4, $exitCode);
        $this->assertStringContainsString('Outcome: lock_busy', Artisan::output());
        $this->assertSame(0, DB::table('media_backfill_reconciliation_events')->count());
    }

    public function test_real_coordinator_lock_acquisition_failure_maps_five_without_attempt(): void
    {
        [, $runId] = $this->candidatePlan();
        $locks = Mockery::mock(MariaDbBackfillLock::class);
        $locks->shouldReceive('acquire')->once()->andReturn(new LockAcquisition(LockAcquireState::Failed));
        $this->app->instance(MariaDbBackfillLock::class, $locks);

        $exitCode = $this->callValid($runId);

        $this->assertSame(5, $exitCode);
        $this->assertStringContainsString('Outcome: lock_acquire_failed', Artisan::output());
        $this->assertSame(0, DB::table('media_backfill_reconciliation_events')->count());
    }

    public function test_real_coordinator_runtime_capability_refusal_maps_six_without_attempt(): void
    {
        [, $runId] = $this->candidatePlan();
        $otherRoot = $this->safetyRoot.'/runtime-other';
        mkdir($otherRoot, 0700);
        config()->set('filesystems.disks.media_local.root', $otherRoot);

        $exitCode = $this->callValid($runId);
        $output = Artisan::output();

        $this->assertSame(6, $exitCode);
        $this->assertStringContainsString('Outcome: safety_failure', $output);
        $this->assertStringContainsString('Attempt: -', $output);
        $this->assertStringContainsString('Durable progress: no', $output);
        $this->assertSame(0, DB::table('media_backfill_reconciliation_events')->count());
    }

    public function test_real_coordinator_missing_durable_run_is_a_safe_refusal_not_cli_usage(): void
    {
        $exitCode = $this->callValid(self::RUN_UUID);
        $output = Artisan::output();

        $this->assertSame(6, $exitCode);
        $this->assertStringContainsString('Outcome: safety_failure', $output);
        $this->assertStringContainsString('Process exit: 6', $output);
        $this->assertSame(0, DB::table('media_backfill_reconciliation_events')->count());
    }

    public function test_real_coordinator_uncertain_release_after_attempt_maps_seven(): void
    {
        [, $runId] = $this->candidatePlan();
        [$locks, $releaseCalls] = $this->lockManagerWithObservedReleaseFailure();
        $this->app->instance(MariaDbBackfillLock::class, $locks);

        $exitCode = $this->callValid($runId);
        $output = Artisan::output();

        $this->assertSame(7, $exitCode);
        $this->assertStringContainsString('Outcome: safety_failure', $output);
        $this->assertStringNotContainsString('Attempt: -', $output);
        $this->assertStringContainsString('Durable progress: unknown', $output);
        $this->assertStringContainsString('Run closure appended: yes', $output);
        $this->assertSame(1, $releaseCalls());
        $this->assertSame(3, DB::table('media_backfill_reconciliation_events')->where('run_id', $runId)->count());
    }

    public function test_old_read_only_command_still_rejects_execute_with_exit_two(): void
    {
        [$exitCode, $output] = $this->callReadOnlyRaw('--run='.self::RUN_UUID.' --execute');

        $this->assertSame(2, $exitCode);
        $this->assertStringContainsString('read-only', $output);
        $this->assertStringNotContainsString('RECONCILIATION MUTATING', $output);
    }

    public function test_command_has_no_direct_journal_database_storage_or_gate_bypass_capability(): void
    {
        $source = file_get_contents(app_path('Console/Commands/ResponsiveBackfillReconcileRunCommand.php'));

        $this->assertStringContainsString('ReconciliationCoordinator', $source);
        $this->assertStringContainsString('new ReconciliationInvocation', $source);
        foreach (['ReconciliationJournal', 'ApplyJournal', 'MariaDbBackfillLock', 'ApplyMaintenanceGuard',
            'ManagedMediaWriterFreezeGuard', 'Storage::', 'DB::', 'media_backfill_', 'recordBlockedAttempt(',
            'recordForwardItemResolution(', 'recordNoEffectItemResolution(', 'closeRunAfterReconciliation(',
            'fileExists(', 'readStream(', 'put(', 'delete(', 'copy(', 'move(', 'listContents',
            "Artisan::call('down'", "Artisan::call('up'"] as $forbidden) {
            $this->assertStringNotContainsString($forbidden, $source, $forbidden);
        }

        $methods = array_map(
            static fn (ReflectionMethod $method): string => $method->getName(),
            (new ReflectionClass(ReconciliationJournal::class))->getMethods(ReflectionMethod::IS_PUBLIC),
        );
        sort($methods);
        $this->assertSame([
            '__construct',
            'beginRunReconciliation',
            'closeRunAfterReconciliation',
            'recordBlockedAttempt',
            'recordForwardItemResolution',
            'recordNoEffectItemResolution',
        ], $methods);
    }

    private function bindReport(ReconciliationReport $report): void
    {
        $coordinator = Mockery::mock(ReconciliationCoordinator::class);
        $coordinator->shouldReceive('run')->once()->andReturn($report);
        $this->app->instance(ReconciliationCoordinator::class, $coordinator);
    }

    private function report(
        ReconciliationOutcome $outcome,
        ?string $attemptId = null,
        ?bool $durableProgress = false,
        ?RecoveryBarrierState $barrier = RecoveryBarrierState::Clear,
    ): ReconciliationReport {
        $noOp = in_array($outcome, [
            ReconciliationOutcome::AlreadyClosed,
            ReconciliationOutcome::NoReconciliationRequired,
        ], true);
        $blocked = $outcome === ReconciliationOutcome::Blocked;
        $completed = $outcome === ReconciliationOutcome::Completed;

        return new ReconciliationReport(
            outcome: $outcome,
            runId: self::RUN_UUID,
            attemptId: $noOp ? null : $attemptId,
            noOp: $noOp,
            itemsTraversed: $completed || $blocked ? 1 : 0,
            itemsSkipped: 0,
            itemsResolvedForward: 0,
            itemsResolvedNoEffect: $completed ? 1 : 0,
            firstBlocker: $blocked ? ReconciliationBlockReason::StorageObservationUntrusted : null,
            runClosureAppended: $completed,
            finalRunState: $completed || $noOp ? RunState::Interrupted : null,
            globalRecoveryBarrier: $barrier,
            durableProgressOccurred: $noOp ? false : $durableProgress,
        );
    }

    private function callValid(string $runId): int
    {
        return Artisan::call('media:responsive-backfill-reconcile-run', [
            '--run' => $runId,
            '--execute' => true,
        ]);
    }

    /** @return array{int, string} */
    private function callRaw(string $arguments): array
    {
        $input = new StringInput('media:responsive-backfill-reconcile-run'.($arguments === '' ? '' : ' '.$arguments));
        $output = new BufferedOutput;
        $exitCode = $this->app->make(ConsoleKernel::class)->handle($input, $output);

        return [$exitCode, $output->fetch()];
    }

    /** @return array{int, string} */
    private function callReadOnlyRaw(string $arguments): array
    {
        $input = new StringInput('media:responsive-backfill-reconcile '.$arguments);
        $output = new BufferedOutput;
        $exitCode = $this->app->make(ConsoleKernel::class)->handle($input, $output);

        return [$exitCode, $output->fetch()];
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
        $this->app->instance(FilesystemManager::class, $filesystems);
    }

    /** @return array<string, mixed> */
    private function immutableApplyFacts(string $runId): array
    {
        $run = (array) DB::table('media_backfill_runs')->where('run_id', $runId)->first();
        $items = DB::table('media_backfill_items')->where('run_id', $runId)->orderBy('id')->get()
            ->map(static fn ($row): array => (array) $row)->all();
        $itemIds = array_map(static fn (array $row): int => (int) $row['id'], $items);
        $objects = DB::table('media_backfill_objects')->whereIn('item_id', $itemIds)->orderBy('id')->get()
            ->map(static fn ($row): array => (array) $row)->all();

        foreach (['state', 'finished_at', 'updated_at', 'reconciliation_event_id'] as $field) {
            unset($run[$field]);
        }
        foreach ($items as &$item) {
            unset($item['reconciliation_result'], $item['reconciliation_event_id']);
        }
        unset($item);
        foreach ($objects as &$object) {
            unset($object['reconciliation_resolution'], $object['reconciliation_event_id']);
        }
        unset($object);

        return compact('run', 'items', 'objects');
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
