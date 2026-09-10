<?php

namespace Tests\Feature;

use App\Models\NewsArticle;
use App\Services\Media\Backfill\ManagedMediaDomain;
use App\Services\Media\Backfill\ManagedMediaReference;
use App\Services\Media\Backfill\PreflightClassification;
use App\Services\Media\Backfill\PreflightResult;
use App\Services\Media\Backfill\Safety\ApplyJournal;
use App\Services\Media\Backfill\Safety\ApplyResult;
use App\Services\Media\Backfill\Safety\ApplyRunSelection;
use App\Services\Media\Backfill\Safety\CleanupState;
use App\Services\Media\Backfill\Safety\CreateReceipt;
use App\Services\Media\Backfill\Safety\CreateState;
use App\Services\Media\Backfill\Safety\RecoveryBarrierState;
use App\Services\Media\Backfill\Safety\RunState;
use App\Services\Media\Backfill\Safety\SafetyError;
use Illuminate\Database\Events\TransactionCommitting;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\Concerns\BackfillSafetyFixtures;
use Tests\TestCase;

class BackfillApplyJournalRangeTest extends TestCase
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

    public function test_typed_selection_is_persisted_atomically_with_exact_json(): void
    {
        $journal = app(ApplyJournal::class);
        $runId = $journal->createApplyRun(
            $this->identity(),
            new ApplyRunSelection(ManagedMediaDomain::Season, 12, 25, 300),
            str_repeat('a', 40),
        );

        $run = $journal->run($runId);
        $this->assertSame('{"domain":"season","after_id":12,"limit":25}', $run->options_json);
        $this->assertSame('{"season":300}', $run->upper_bounds_json);
        $this->assertSame('{"season":12}', $run->checkpoints_json);
        $this->assertSame($this->identity()->hash, $run->storage_identity_hash);
        $this->assertSame(str_repeat('a', 40), $run->code_revision);
    }

    public function test_selection_rejects_invalid_bounds_and_revision_without_a_run(): void
    {
        $this->assertSafetyError(SafetyError::InvalidInput,
            fn () => new ApplyRunSelection(ManagedMediaDomain::News, -1, 1, 1));
        $this->assertSafetyError(SafetyError::InvalidInput,
            fn () => new ApplyRunSelection(ManagedMediaDomain::News, 0, 0, 1));
        $this->assertSafetyError(SafetyError::InvalidInput,
            fn () => new ApplyRunSelection(ManagedMediaDomain::News, 0, 1001, 1));
        $this->assertSafetyError(SafetyError::InvalidInput,
            fn () => new ApplyRunSelection(ManagedMediaDomain::News, 0, 1, -1));

        $journal = app(ApplyJournal::class);
        $this->assertSafetyError(SafetyError::InvalidInput,
            fn () => $journal->createApplyRun($this->identity(), $this->applySelection(), 'not-a-revision'));
        $this->assertSame(0, DB::table('media_backfill_runs')->count());
    }

    public function test_empty_range_keeps_after_id_as_checkpoint_without_fake_evidence(): void
    {
        $journal = app(ApplyJournal::class);
        $runId = $journal->createApplyRun(
            $this->identity(),
            new ApplyRunSelection(ManagedMediaDomain::News, 50, 10, 40),
        );

        $this->assertSame('{"news":50}', $journal->run($runId)->checkpoints_json);
        $journal->advanceCheckpoint($runId, ManagedMediaDomain::News, 50);
        $this->assertSame('{"news":50}', $journal->run($runId)->checkpoints_json);
        $this->assertSafetyError(SafetyError::IllegalTransition,
            fn () => $journal->snapshot($runId, $this->excludedReference(ManagedMediaDomain::News, 51)));
        $this->assertSafetyError(SafetyError::IllegalTransition,
            fn () => $journal->advanceCheckpoint($runId, ManagedMediaDomain::News, 51));
        $this->assertSame(0, DB::table('media_backfill_items')->count());
    }

    public function test_run_insert_rolls_back_completely_when_commit_fails(): void
    {
        DB::connection()->getEventDispatcher()->listen(TransactionCommitting::class, function () {
            throw new RuntimeException('must be sanitized');
        });

        try {
            $this->assertSafetyError(SafetyError::JournalUnavailable,
                fn () => app(ApplyJournal::class)->createApplyRun($this->identity(), $this->applySelection()));
            $this->assertSame(0, DB::table('media_backfill_runs')->count());
            $this->assertSame(0, DB::transactionLevel());
            $this->assertFalse(DB::connection()->getPdo()->inTransaction());
        } finally {
            DB::connection()->getEventDispatcher()->forget(TransactionCommitting::class);
        }
    }

    public function test_snapshot_enforces_selected_domain_range_and_limit(): void
    {
        $journal = app(ApplyJournal::class);
        $runId = $journal->createApplyRun(
            $this->identity(),
            new ApplyRunSelection(ManagedMediaDomain::News, 10, 2, 50),
        );

        $journal->snapshot($runId, $this->excludedReference(ManagedMediaDomain::News, 11));
        $journal->snapshot($runId, $this->excludedReference(ManagedMediaDomain::News, 30));
        $this->assertSafetyError(SafetyError::IllegalTransition,
            fn () => $journal->snapshot($runId, $this->excludedReference(ManagedMediaDomain::News, 40)));
        $this->assertSafetyError(SafetyError::IllegalTransition,
            fn () => $journal->snapshot($runId, $this->excludedReference(ManagedMediaDomain::News, 10)));
        $this->assertSafetyError(SafetyError::IllegalTransition,
            fn () => $journal->snapshot($runId, $this->excludedReference(ManagedMediaDomain::News, 51)));
        $this->assertSafetyError(SafetyError::IllegalTransition,
            fn () => $journal->snapshot($runId, $this->excludedReference(ManagedMediaDomain::Season, 20)));
        $this->assertSame(2, DB::table('media_backfill_items')->count());
    }

    public function test_checkpoint_advances_over_sparse_finished_evidence_and_never_regresses(): void
    {
        $news = NewsArticle::factory()->create();
        $before = $news->fresh()->getAttributes();
        $journal = app(ApplyJournal::class);
        $runId = $journal->createApplyRun(
            $this->identity(),
            new ApplyRunSelection(ManagedMediaDomain::News, 5, 10, 100),
        );
        $this->finishSkipped($journal, $runId, 20);
        $this->finishSkipped($journal, $runId, 70);

        $journal->advanceCheckpoint($runId, ManagedMediaDomain::News, 70);
        $this->assertSame('{"news":70}', $journal->run($runId)->checkpoints_json);
        $journal->advanceCheckpoint($runId, ManagedMediaDomain::News, 70);
        $this->assertSame('{"news":70}', $journal->run($runId)->checkpoints_json);
        $this->assertSafetyError(SafetyError::IllegalTransition,
            fn () => $journal->advanceCheckpoint($runId, ManagedMediaDomain::News, 20));
        $this->assertSafetyError(SafetyError::InvalidInput,
            fn () => $journal->advanceCheckpoint($runId, ManagedMediaDomain::Season, 70));
        $this->assertSafetyError(SafetyError::InvalidInput,
            fn () => $journal->advanceCheckpoint($runId, ManagedMediaDomain::News, -1));
        $this->assertSafetyError(SafetyError::IllegalTransition,
            fn () => $journal->advanceCheckpoint($runId, ManagedMediaDomain::News, 101));
        $this->assertSame($before, $news->fresh()->getAttributes());
        $this->assertSame([], File::allFiles($this->safetyRoot));
    }

    public function test_checkpoint_requires_terminal_target_and_no_unfinished_item_in_interval(): void
    {
        $journal = app(ApplyJournal::class);
        $runId = $journal->createApplyRun(
            $this->identity(),
            new ApplyRunSelection(ManagedMediaDomain::News, 0, 10, 100),
        );
        $unfinished = $journal->snapshot($runId, $this->excludedReference(ManagedMediaDomain::News, 20));
        $finished = $this->finishSkipped($journal, $runId, 50);

        $this->assertSafetyError(SafetyError::IllegalTransition,
            fn () => $journal->advanceCheckpoint($runId, ManagedMediaDomain::News, 20));
        $this->assertSafetyError(SafetyError::IllegalTransition,
            fn () => $journal->advanceCheckpoint($runId, ManagedMediaDomain::News, 50));
        $journal->finishItem($unfinished, ApplyResult::Skipped);
        $journal->advanceCheckpoint($runId, ManagedMediaDomain::News, 50);
        $this->assertSame(ApplyResult::Skipped->value, $journal->item($finished)->apply_result);
        $journal->finishRun($runId, RunState::Completed);
        $this->assertSafetyError(SafetyError::IllegalTransition,
            fn () => $journal->advanceCheckpoint($runId, ManagedMediaDomain::News, 50));
    }

    public function test_recovery_barrier_blocks_active_unfinished_and_unresolved_history(): void
    {
        $journal = app(ApplyJournal::class);
        $this->assertSame(RecoveryBarrierState::Clear, $journal->recoveryBarrier());

        $activeRun = $journal->createApplyRun($this->identity(), $this->applySelection());
        $this->assertSame(RecoveryBarrierState::Blocked, $journal->recoveryBarrier());
        $journal->finishRun($activeRun, RunState::Failed);
        $this->assertSame(RecoveryBarrierState::Clear, $journal->recoveryBarrier());

        $unfinishedRun = $journal->createApplyRun($this->identity(), $this->applySelection());
        $journal->snapshot($unfinishedRun, $this->excludedReference(ManagedMediaDomain::News, 10));
        $journal->finishRun($unfinishedRun, RunState::Failed);
        $this->assertSame(RecoveryBarrierState::Blocked, $journal->recoveryBarrier());
    }

    #[DataProvider('unresolvedWriteStates')]
    public function test_recovery_barrier_blocks_unresolved_write_states(?CreateState $receipt): void
    {
        [$journal, $run, $item, $object] = $this->plannedObject();
        $journal->commitIntent($object);
        if ($receipt !== null) {
            $journal->recordReceipt($object, new CreateReceipt($receipt));
        }
        $journal->finishItem($item, ApplyResult::PublicationUnknown);
        $journal->finishRun($run, RunState::Interrupted);

        $this->assertSame(RecoveryBarrierState::Blocked, $journal->recoveryBarrier());
    }

    public static function unresolvedWriteStates(): array
    {
        return ['intent' => [null], 'unknown' => [CreateState::Unknown]];
    }

    #[DataProvider('unresolvedCleanupStates')]
    public function test_recovery_barrier_blocks_unresolved_cleanup_states(CleanupState $state): void
    {
        [$journal, $run, $item, $object] = $this->plannedObject();
        $journal->commitIntent($object);
        $journal->recordReceipt($object, new CreateReceipt(CreateState::Created));
        $journal->updateCleanup($object, CleanupState::Pending);
        if ($state !== CleanupState::Pending) {
            $journal->updateCleanup($object, $state);
        }
        $journal->finishItem($item, ApplyResult::FailedCleanupIncomplete);
        $journal->finishRun($run, RunState::Failed);

        $this->assertSame(RecoveryBarrierState::Blocked, $journal->recoveryBarrier());
    }

    public static function unresolvedCleanupStates(): array
    {
        return [
            'pending' => [CleanupState::Pending],
            'failed' => [CleanupState::Failed],
            'unknown' => [CleanupState::Unknown],
        ];
    }

    #[DataProvider('resolvedNoWriteReceipts')]
    public function test_recovery_barrier_allows_resolved_terminal_no_write_receipts(CreateState $state, ApplyResult $result): void
    {
        [$journal, $run, $item, $object] = $this->plannedObject();
        $journal->commitIntent($object);
        $journal->recordReceipt($object, new CreateReceipt($state));
        $journal->finishItem($item, $result);
        $journal->finishRun($run, RunState::Failed);

        $this->assertSame(RecoveryBarrierState::Clear, $journal->recoveryBarrier());
    }

    public static function resolvedNoWriteReceipts(): array
    {
        return [
            'known failure' => [CreateState::Failed, ApplyResult::FailedNoWrites],
            'rejected collision' => [CreateState::Rejected, ApplyResult::CollisionDetected],
        ];
    }

    public function test_recovery_barrier_allows_resolved_terminal_history_and_is_read_only(): void
    {
        $journal = app(ApplyJournal::class);
        $runId = $journal->createApplyRun($this->identity(), $this->applySelection());
        $this->finishSkipped($journal, $runId, 50);
        $journal->finishRun($runId, RunState::Completed);
        $before = (array) $journal->run($runId);
        $queries = [];
        DB::listen(function ($query) use (&$queries) {
            $queries[] = $query->sql;
        });

        $this->assertSame(RecoveryBarrierState::Clear, $journal->recoveryBarrier());
        $this->assertNotEmpty($queries);
        $this->assertSame([], array_values(array_filter(
            $queries,
            fn (string $sql) => preg_match('/\A\s*(insert|update|delete|replace|alter|create|drop|truncate)\b/i', $sql) === 1,
        )));
        $this->assertSame($before, (array) $journal->run($runId));
        $this->assertSame([], File::allFiles($this->safetyRoot));
    }

    private function excludedReference(ManagedMediaDomain $domain, int $entityId): PreflightResult
    {
        return new PreflightResult(
            new ManagedMediaReference($domain, $entityId, null),
            PreflightClassification::ExcludedNull,
        );
    }

    private function finishSkipped(ApplyJournal $journal, string $runId, int $entityId): int
    {
        $itemId = $journal->snapshot($runId, $this->excludedReference(ManagedMediaDomain::News, $entityId));
        $journal->finishItem($itemId, ApplyResult::Skipped);

        return $itemId;
    }
}
