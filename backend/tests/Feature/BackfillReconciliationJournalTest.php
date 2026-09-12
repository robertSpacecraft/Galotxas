<?php

namespace Tests\Feature;

use App\Services\Media\Backfill\Reconciliation\ForwardObjectEvidence;
use App\Services\Media\Backfill\Reconciliation\ItemReconciliationResult;
use App\Services\Media\Backfill\Reconciliation\ReconciliationBackendMode;
use App\Services\Media\Backfill\Reconciliation\ReconciliationBlockReason;
use App\Services\Media\Backfill\Reconciliation\ReconciliationError;
use App\Services\Media\Backfill\Reconciliation\ReconciliationEventType;
use App\Services\Media\Backfill\Reconciliation\ReconciliationException;
use App\Services\Media\Backfill\Reconciliation\ReconciliationJournal;
use App\Services\Media\Backfill\Safety\ApplyJournal;
use App\Services\Media\Backfill\Safety\ApplyMaintenanceGuard;
use App\Services\Media\Backfill\Safety\ApplyResult;
use App\Services\Media\Backfill\Safety\BackfillSafetyException;
use App\Services\Media\Backfill\Safety\CleanupState;
use App\Services\Media\Backfill\Safety\CreateReceipt;
use App\Services\Media\Backfill\Safety\CreateState;
use App\Services\Media\Backfill\Safety\RecoveryBarrierState;
use App\Services\Media\Backfill\Safety\RunState;
use App\Services\Media\Backfill\Safety\SafetyError;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Mockery;
use PHPUnit\Framework\Attributes\DataProvider;
use ReflectionClass;
use ReflectionMethod;
use RuntimeException;
use Tests\Concerns\BackfillSafetyFixtures;
use Tests\TestCase;

class BackfillReconciliationJournalTest extends TestCase
{
    use BackfillSafetyFixtures;
    use DatabaseTruncation;

    private const EVENTS = 'media_backfill_reconciliation_events';

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

    public function test_attempt_started_records_provenance_for_active_and_terminal_runs(): void
    {
        [$journal, $runId, $itemId] = $this->candidatePlan();
        $history = $this->applyHistory();

        $record = $this->journal()->beginRunReconciliation($runId, $this->reconciliationUuid(1), $this->reconciliationMoment(), $this->reconciliationContext());

        $this->assertSame(ReconciliationEventType::AttemptStarted, $record->type);
        $this->assertFalse($record->replayed);
        $this->assertNull($record->itemResult);
        $this->assertSame(0, $record->objectsResolved);
        $this->assertSame(ReconciliationJournal::EVIDENCE_VERSION, $record->evidenceVersion);
        $this->assertSame(substr(hash('sha256', $this->reconciliationUuid(1)), 0, 16), $record->eventFingerprint);
        $this->assertSame(substr(hash('sha256', $this->reconciliationUuid(90)), 0, 16), $record->attemptFingerprint);

        $event = DB::table(self::EVENTS)->where('event_id', $this->reconciliationUuid(1))->first();
        $this->assertSame($this->reconciliationUuid(90), $event->attempt_id);
        $this->assertSame($runId, $event->run_id);
        $this->assertNull($event->item_id);
        $this->assertSame('attempt_started', $event->event_type);
        $this->assertSame(1, (int) $event->evidence_version);
        $this->assertSame($this->identity()->hash, $event->storage_identity_hash);
        $this->assertSame('local', $event->backend_mode);
        $this->assertSame(str_repeat('a', 40), $event->code_revision);
        $this->assertSame(
            '{"v":1,"kind":"attempt_started","observed_at":"2026-09-12T10:00:00Z","storage_identity_hash":"'
                .$this->identity()->hash.'","backend_mode":"local"}',
            $event->evidence_json,
        );
        $this->assertSame(hash('sha256', $event->evidence_json), $event->evidence_sha256);
        $this->assertSame($history, $this->applyHistory());
        $this->assertNull(DB::table('media_backfill_runs')->where('run_id', $runId)->value('reconciliation_event_id'));
        $this->assertNull(DB::table('media_backfill_items')->where('id', $itemId)->value('reconciliation_result'));

        $journal->finishItem($itemId, ApplyResult::Skipped);
        $journal->finishRun($runId, RunState::Completed, ['skipped' => 1]);
        $terminal = $this->journal()->beginRunReconciliation($runId, $this->reconciliationUuid(2), $this->reconciliationMoment(), $this->reconciliationContext(91));
        $this->assertFalse($terminal->replayed);
        $this->assertSame(2, DB::table(self::EVENTS)->count());
    }

    public function test_attempt_replay_is_idempotent_and_every_conflict_fails_closed(): void
    {
        [, $runId] = $this->candidatePlan();
        $context = $this->reconciliationContext();
        $this->journal()->beginRunReconciliation($runId, $this->reconciliationUuid(1), $this->reconciliationMoment(), $context);

        $replay = $this->journal()->beginRunReconciliation($runId, $this->reconciliationUuid(1), $this->reconciliationMoment(), $context);
        $this->assertTrue($replay->replayed);

        foreach ([
            'different observation time' => fn () => $this->journal()
                ->beginRunReconciliation($runId, $this->reconciliationUuid(1), $this->reconciliationMoment('11:00:00'), $context),
            'different code revision' => fn () => $this->journal()
                ->beginRunReconciliation($runId, $this->reconciliationUuid(1), $this->reconciliationMoment(), $this->reconciliationContext(90, false)),
            'different attempt' => fn () => $this->journal()
                ->beginRunReconciliation($runId, $this->reconciliationUuid(1), $this->reconciliationMoment(), $this->reconciliationContext(92)),
            'second start for one attempt' => fn () => $this->journal()
                ->beginRunReconciliation($runId, $this->reconciliationUuid(3), $this->reconciliationMoment(), $context),
        ] as $name => $callback) {
            $this->assertReconciliationError(ReconciliationError::ReplayConflict, $callback, $name);
        }
        $this->assertSame(1, DB::table(self::EVENTS)->count());
    }

    public function test_attempt_requires_the_exact_storage_identity_of_the_selected_run(): void
    {
        [, $runId] = $this->candidatePlan();
        DB::table('media_backfill_runs')->where('run_id', $runId)->update(['storage_identity_hash' => str_repeat('b', 64)]);

        $this->assertReconciliationError(
            ReconciliationError::IdentityMismatch,
            fn () => $this->journal()->beginRunReconciliation($runId, $this->reconciliationUuid(1), $this->reconciliationMoment(), $this->reconciliationContext()),
        );
        $this->assertReconciliationError(
            ReconciliationError::IdentityMismatch,
            fn () => $this->journal()->beginRunReconciliation($runId, $this->reconciliationUuid(1), $this->reconciliationMoment(),
                $this->reconciliationContext(90, true, ReconciliationBackendMode::S3)),
        );
        $this->assertSame(0, DB::table(self::EVENTS)->count());
    }

    public function test_attempt_requires_maintenance_outside_testing_semantics(): void
    {
        [, $runId] = $this->candidatePlan();
        $guard = Mockery::mock(ApplyMaintenanceGuard::class);
        $guard->shouldReceive('assertAllowed')->andThrow(new BackfillSafetyException(SafetyError::MaintenanceRequired));
        $this->instance(ApplyMaintenanceGuard::class, $guard);

        $this->assertReconciliationError(
            ReconciliationError::MaintenanceRequired,
            fn () => $this->journal()->beginRunReconciliation($runId, $this->reconciliationUuid(1), $this->reconciliationMoment(), $this->reconciliationContext()),
        );
        $this->assertSame(0, DB::table(self::EVENTS)->count());
    }

    public function test_attempt_requires_a_still_owned_advisory_lock(): void
    {
        [, $runId] = $this->candidatePlan();
        $context = $this->reconciliationContext();
        DB::unprepared('KILL CONNECTION '.(int) $this->backfillLock()->connectionId);

        $this->assertReconciliationError(
            ReconciliationError::LockLost,
            fn () => $this->journal()->beginRunReconciliation($runId, $this->reconciliationUuid(1), $this->reconciliationMoment(), $context),
        );
        $this->assertSame(0, DB::table(self::EVENTS)->count());
    }

    public function test_blocked_attempt_records_a_closed_reason_without_resolving_anything(): void
    {
        [$journal, $runId, $itemId] = $this->candidatePlan();
        $context = $this->reconciliationContext();

        $this->assertReconciliationError(
            ReconciliationError::IllegalResolution,
            fn () => $this->journal()->recordBlockedAttempt($runId, $itemId, $this->reconciliationUuid(2),
                ReconciliationBlockReason::EvidenceMismatch, $this->reconciliationMoment(), $context),
            'blocked before started',
        );

        $this->journal()->beginRunReconciliation($runId, $this->reconciliationUuid(1), $this->reconciliationMoment(), $context);
        $history = $this->applyHistory();
        $record = $this->journal()->recordBlockedAttempt($runId, $itemId, $this->reconciliationUuid(2),
            ReconciliationBlockReason::DomainRevalidationFailed, $this->reconciliationMoment(), $context);

        $this->assertSame(ReconciliationEventType::AttemptBlocked, $record->type);
        $this->assertFalse($record->replayed);
        $this->assertNull($record->itemResult);
        $event = DB::table(self::EVENTS)->where('event_id', $this->reconciliationUuid(2))->first();
        $this->assertSame($itemId, (int) $event->item_id);
        $this->assertSame('attempt_blocked', $event->event_type);
        $this->assertStringContainsString('"reason":"domain_revalidation_failed"', $event->evidence_json);
        $this->assertSame(hash('sha256', $event->evidence_json), $event->evidence_sha256);

        $this->assertTrue($this->journal()->recordBlockedAttempt($runId, $itemId, $this->reconciliationUuid(2),
            ReconciliationBlockReason::DomainRevalidationFailed, $this->reconciliationMoment(), $context)->replayed);
        $this->assertReconciliationError(
            ReconciliationError::ReplayConflict,
            fn () => $this->journal()->recordBlockedAttempt($runId, $itemId, $this->reconciliationUuid(2),
                ReconciliationBlockReason::ResolutionConflict, $this->reconciliationMoment(), $context),
        );
        $runLevel = $this->journal()->recordBlockedAttempt($runId, null, $this->reconciliationUuid(4),
            ReconciliationBlockReason::StorageObservationUntrusted, $this->reconciliationMoment(), $context);
        $this->assertFalse($runLevel->replayed);
        $this->assertNull(DB::table(self::EVENTS)->where('event_id', $this->reconciliationUuid(4))->value('item_id'));

        $this->assertSame(3, DB::table(self::EVENTS)->count());
        $this->assertSame($history, $this->applyHistory());
        $this->assertNull(DB::table('media_backfill_items')->where('id', $itemId)->value('reconciliation_result'));
        $this->assertSame(RecoveryBarrierState::Blocked, $journal->recoveryBarrier());
    }

    public function test_blocked_attempt_rejects_an_item_outside_the_selected_run(): void
    {
        [$journal, $runId, $itemId] = $this->candidatePlan();
        $other = $journal->createApplyRun($this->identity(), $this->applySelection());
        $context = $this->reconciliationContext();
        $this->journal()->beginRunReconciliation($other, $this->reconciliationUuid(1), $this->reconciliationMoment(), $context);

        $this->assertReconciliationError(
            ReconciliationError::InconsistentJournal,
            fn () => $this->journal()->recordBlockedAttempt($other, $itemId, $this->reconciliationUuid(2),
                ReconciliationBlockReason::InconsistentJournal, $this->reconciliationMoment(), $context),
        );
        $this->assertReconciliationError(
            ReconciliationError::ReplayConflict,
            fn () => $this->journal()->recordBlockedAttempt($runId, $itemId, $this->reconciliationUuid(2),
                ReconciliationBlockReason::InconsistentJournal, $this->reconciliationMoment(), $context),
            'one attempt may not span two runs',
        );
        $this->assertSame(1, DB::table(self::EVENTS)->count());
    }

    public function test_forward_acceptance_is_item_atomic_and_preserves_apply_history(): void
    {
        [$journal, $runId, $itemId] = $this->candidatePlan();
        $journal->commitIntent($this->objectIds($itemId)[0]);
        $context = $this->reconciliationContext();
        $this->journal()->beginRunReconciliation($runId, $this->reconciliationUuid(1), $this->reconciliationMoment(), $context);
        $history = $this->applyHistory();

        $record = $this->journal()->recordForwardItemResolution($runId, $itemId, $this->reconciliationUuid(2),
            $this->forwardEvidenceFor($itemId), $context);

        $this->assertSame(ReconciliationEventType::ItemForwardAccepted, $record->type);
        $this->assertSame(ItemReconciliationResult::ForwardAccepted, $record->itemResult);
        $this->assertSame(count($this->objectIds($itemId)), $record->objectsResolved);
        $this->assertFalse($record->replayed);
        $this->assertForwardProjected($itemId, $this->reconciliationUuid(2));
        $this->assertSame($history, $this->applyHistory());
        $this->assertSame(RecoveryBarrierState::Blocked, $journal->recoveryBarrier());

        $event = DB::table(self::EVENTS)->where('event_id', $this->reconciliationUuid(2))->first();
        $this->assertSame($itemId, (int) $event->item_id);
        $this->assertSame(hash('sha256', $event->evidence_json), $event->evidence_sha256);
        $this->assertStringStartsWith('{"v":1,"kind":"item_forward_accepted","observed_at":"2026-09-12T10:00:00Z"', $event->evidence_json);
        $this->assertStringContainsString('"unique_live_owner_revalidated":true', $event->evidence_json);
        $this->assertStringContainsString('"no_unexpected_canonical_target":true', $event->evidence_json);
        $this->assertStringContainsString('"objects":[{"object_id":'.$this->objectIds($itemId)[0], $event->evidence_json);
        $this->assertStringNotContainsString('variants/v1/', $event->evidence_json);
        $this->assertStringNotContainsString($this->safetyRoot, $event->evidence_json);

        $replay = $this->journal()->recordForwardItemResolution($runId, $itemId, $this->reconciliationUuid(2),
            $this->forwardEvidenceFor($itemId), $context);
        $this->assertTrue($replay->replayed);
        $this->assertSame(ItemReconciliationResult::ForwardAccepted, $replay->itemResult);
        $this->assertReconciliationError(
            ReconciliationError::ReplayConflict,
            fn () => $this->journal()->recordForwardItemResolution($runId, $itemId, $this->reconciliationUuid(3),
                $this->forwardEvidenceFor($itemId), $context),
            'a second event may not replace a terminal resolution',
        );
        $this->assertSame(2, DB::table(self::EVENTS)->count());
        $this->assertSame($history, $this->applyHistory());
    }

    #[DataProvider('forwardHistories')]
    public function test_forward_acceptance_accepts_any_history_without_deleted_cleanup(string $scenario): void
    {
        [$journal, $runId, $itemId] = $this->candidatePlan();
        $this->applyScenario($journal, $itemId, $scenario);
        $context = $this->reconciliationContext();
        $this->journal()->beginRunReconciliation($runId, $this->reconciliationUuid(1), $this->reconciliationMoment(), $context);
        $history = $this->applyHistory();

        $record = $this->journal()->recordForwardItemResolution($runId, $itemId, $this->reconciliationUuid(2),
            $this->forwardEvidenceFor($itemId), $context);

        $this->assertSame(ItemReconciliationResult::ForwardAccepted, $record->itemResult);
        $this->assertSame(count($this->objectIds($itemId)), $record->objectsResolved);
        $this->assertForwardProjected($itemId, $this->reconciliationUuid(2));
        $this->assertSame($history, $this->applyHistory());
    }

    public static function forwardHistories(): array
    {
        return [
            'not dispatched' => ['not_dispatched'],
            'ambiguous intent' => ['intent'],
            'ambiguous unknown' => ['unknown'],
            'created receipt' => ['created'],
            'rejected collision' => ['rejected'],
            'failed without write' => ['failed'],
            'cleanup pending' => ['cleanup_pending'],
            'cleanup failed' => ['cleanup_failed'],
            'cleanup unknown' => ['cleanup_unknown'],
            'finished publication unknown' => ['finished_publication_unknown'],
            'finished cleanup incomplete' => ['finished_cleanup_incomplete'],
        ];
    }

    public function test_forward_acceptance_rejects_a_deleted_cleanup_state(): void
    {
        [$journal, $runId, $itemId] = $this->candidatePlan();
        $this->applyScenario($journal, $itemId, 'cleanup_deleted');
        $context = $this->reconciliationContext();
        $this->journal()->beginRunReconciliation($runId, $this->reconciliationUuid(1), $this->reconciliationMoment(), $context);
        $history = $this->applyHistory();

        $this->assertReconciliationError(
            ReconciliationError::IllegalResolution,
            fn () => $this->journal()->recordForwardItemResolution($runId, $itemId, $this->reconciliationUuid(2),
                $this->forwardEvidenceFor($itemId), $context),
        );
        $this->assertNull(DB::table('media_backfill_items')->where('id', $itemId)->value('reconciliation_result'));
        $this->assertSame(1, DB::table(self::EVENTS)->count());
        $this->assertSame($history, $this->applyHistory());
    }

    public function test_forward_acceptance_rejects_evidence_that_contradicts_durable_facts(): void
    {
        [, $runId, $itemId] = $this->candidatePlan();
        $context = $this->reconciliationContext();
        $this->journal()->beginRunReconciliation($runId, $this->reconciliationUuid(1), $this->reconciliationMoment(), $context);
        $ids = $this->objectIds($itemId);
        $observed = $this->observedObjects($itemId);

        $cases = [
            'missing object' => [['objects' => array_slice($observed, 1)], []],
            'extra object' => [['objects' => [...$observed, new ForwardObjectEvidence(
                max($ids) + 1, str_repeat('c', 64), 10, 'image/webp', true, true)]], []],
            'observed sha mismatch' => [[], ['observedSha256' => str_repeat('c', 64)]],
            'observed size mismatch' => [[], ['observedSize' => 999999]],
            'observed mime mismatch' => [[], ['observedMimeType' => 'application/json']],
            'descriptor not validated' => [[], ['descriptorValidated' => false]],
            'structure not validated' => [[], ['structureValidated' => false]],
            'reference identity' => [['referenceIdentitySha256' => str_repeat('c', 64)], []],
            'master key' => [['masterKeySha256' => str_repeat('c', 64)], []],
            'master content' => [['masterSha256' => str_repeat('c', 64)], []],
            'candidate manifest' => [['candidateManifestSha256' => str_repeat('c', 64)], []],
            'live owner attestation' => [['uniqueLiveOwnerRevalidated' => false], []],
            'live owner domain' => [['liveOwnerDomain' => 'season'], []],
            'live owner entity' => [['liveOwnerEntityId' => 999], []],
            'manifest structure' => [['manifestStructureValidated' => false], []],
            'unexpected canonical target' => [['noUnexpectedCanonicalTarget' => false], []],
            'storage identity' => [['storageIdentityHash' => str_repeat('c', 64)], []],
            'backend mode' => [['backendMode' => ReconciliationBackendMode::S3], []],
        ];
        foreach ($cases as $name => [$overrides, $objectOverrides]) {
            $this->assertReconciliationError(
                ReconciliationError::EvidenceMismatch,
                fn () => $this->journal()->recordForwardItemResolution($runId, $itemId, $this->reconciliationUuid(2),
                    $this->forwardEvidenceFor($itemId, $overrides, $objectOverrides), $context),
                $name,
            );
        }
        $this->assertSame(1, DB::table(self::EVENTS)->count());
        $this->assertNull(DB::table('media_backfill_items')->where('id', $itemId)->value('reconciliation_result'));
    }

    public function test_forward_evidence_rejects_duplicate_or_malformed_observations(): void
    {
        [, , $itemId] = $this->candidatePlan();
        $observed = $this->observedObjects($itemId);

        foreach ([
            'duplicate object' => fn () => $this->forwardEvidenceFor($itemId, ['objects' => [$observed[0], $observed[0]]]),
            'unordered objects' => fn () => $this->forwardEvidenceFor($itemId, ['objects' => array_reverse($observed)]),
            'empty objects' => fn () => $this->forwardEvidenceFor($itemId, ['objects' => []]),
            'invalid hash' => fn () => $this->forwardEvidenceFor($itemId, ['masterSha256' => 'not-a-hash']),
            'invalid observed hash' => fn () => new ForwardObjectEvidence(1, 'nope', 10, 'image/webp', true, true),
            'invalid observed size' => fn () => new ForwardObjectEvidence(1, str_repeat('c', 64), 0, 'image/webp', true, true),
        ] as $name => $callback) {
            $this->assertReconciliationError(ReconciliationError::InvalidInput, $callback, $name);
        }
    }

    public function test_forward_acceptance_rejects_broken_parentage_plan_or_durable_state(): void
    {
        [$journal, $runId, $itemId] = $this->candidatePlan();
        $other = $journal->createApplyRun($this->identity(), $this->applySelection());
        $context = $this->reconciliationContext();
        $this->journal()->beginRunReconciliation($runId, $this->reconciliationUuid(1), $this->reconciliationMoment(), $context);
        $ids = $this->objectIds($itemId);

        $this->assertReconciliationError(
            ReconciliationError::InconsistentJournal,
            fn () => $this->journal()->recordForwardItemResolution($other, $itemId, $this->reconciliationUuid(2),
                $this->forwardEvidenceFor($itemId), $context),
            'item outside the selected run',
        );

        DB::table('media_backfill_objects')->where('id', $ids[0])->update(['write_state' => 'created']);
        $this->assertReconciliationError(
            ReconciliationError::InconsistentJournal,
            fn () => $this->journal()->recordForwardItemResolution($runId, $itemId, $this->reconciliationUuid(2),
                $this->forwardEvidenceFor($itemId), $context),
            'impossible original state combination',
        );
        DB::table('media_backfill_objects')->where('id', $ids[0])->update(['write_state' => 'planned']);

        DB::table('media_backfill_objects')->where('id', max($ids))->delete();
        $this->assertReconciliationError(
            ReconciliationError::InconsistentJournal,
            fn () => $this->journal()->recordForwardItemResolution($runId, $itemId, $this->reconciliationUuid(2),
                $this->forwardEvidenceFor($itemId), $context),
            'incomplete planned set',
        );

        DB::table('media_backfill_items')->where('id', $itemId)->update(['entity_id' => 5000]);
        $this->assertReconciliationError(
            ReconciliationError::InconsistentJournal,
            fn () => $this->journal()->recordForwardItemResolution($runId, $itemId, $this->reconciliationUuid(2),
                $this->forwardEvidenceFor($itemId), $context),
            'item outside the durable selection range',
        );
        $this->assertSame(1, DB::table(self::EVENTS)->count());
        $this->assertNull(DB::table('media_backfill_items')->where('id', $itemId)->value('reconciliation_result'));
    }

    public function test_forward_acceptance_refuses_unknown_partial_or_missing_projections(): void
    {
        [, $runId, $itemId] = $this->candidatePlan();
        $context = $this->reconciliationContext();
        $this->journal()->beginRunReconciliation($runId, $this->reconciliationUuid(1), $this->reconciliationMoment(), $context);
        $this->journal()->recordForwardItemResolution($runId, $itemId, $this->reconciliationUuid(2), $this->forwardEvidenceFor($itemId), $context);
        $ids = $this->objectIds($itemId);
        $resolve = fn () => $this->journal()->recordForwardItemResolution($runId, $itemId, $this->reconciliationUuid(2),
            $this->forwardEvidenceFor($itemId), $context);

        DB::table('media_backfill_objects')->where('id', $ids[0])
            ->update(['reconciliation_resolution' => null, 'reconciliation_event_id' => null]);
        $this->assertReconciliationError(ReconciliationError::InconsistentJournal, $resolve, 'partial projection');
        $this->assertNull(DB::table('media_backfill_objects')->where('id', $ids[0])->value('reconciliation_resolution'));

        DB::table('media_backfill_items')->where('id', $itemId)
            ->update(['reconciliation_result' => null, 'reconciliation_event_id' => null]);
        DB::table('media_backfill_objects')->where('item_id', $itemId)
            ->update(['reconciliation_resolution' => null, 'reconciliation_event_id' => null]);
        $this->assertReconciliationError(ReconciliationError::InconsistentJournal, $resolve, 'event without projections');

        DB::table('media_backfill_objects')->where('id', $ids[0])
            ->update(['reconciliation_resolution' => 'future_unknown', 'reconciliation_event_id' => $this->reconciliationUuid(2)]);
        $this->assertReconciliationError(ReconciliationError::InconsistentJournal, $resolve, 'unknown projection value');
    }

    public function test_no_effect_closes_only_the_item_phase_blocker(): void
    {
        [$journal, $runId, $itemId] = $this->candidatePlan();
        $context = $this->reconciliationContext();
        $this->journal()->beginRunReconciliation($runId, $this->reconciliationUuid(1), $this->reconciliationMoment(), $context);
        $history = $this->applyHistory();

        $record = $this->journal()->recordNoEffectItemResolution($runId, $itemId, $this->reconciliationUuid(2), $this->reconciliationMoment(), $context);

        $this->assertSame(ReconciliationEventType::ItemNoEffectClosed, $record->type);
        $this->assertSame(ItemReconciliationResult::ClosedNoEffect, $record->itemResult);
        $this->assertSame(0, $record->objectsResolved);
        $item = DB::table('media_backfill_items')->where('id', $itemId)->first();
        $this->assertSame('closed_no_effect', $item->reconciliation_result);
        $this->assertSame($this->reconciliationUuid(2), $item->reconciliation_event_id);
        $this->assertSame('revalidated', $item->phase);
        $this->assertNull($item->apply_result);
        $this->assertNull($item->finished_at);
        foreach (DB::table('media_backfill_objects')->where('item_id', $itemId)->get() as $row) {
            $this->assertNull($row->reconciliation_resolution);
            $this->assertNull($row->reconciliation_event_id);
        }
        $event = DB::table(self::EVENTS)->where('event_id', $this->reconciliationUuid(2))->first();
        $this->assertSame('item_no_effect_closed', $event->event_type);
        $this->assertStringContainsString('"attribution":"not_dispatched"', $event->evidence_json);
        $this->assertStringContainsString('"cleanup_state":"not_required"', $event->evidence_json);
        $this->assertSame(hash('sha256', $event->evidence_json), $event->evidence_sha256);
        $this->assertSame($history, $this->applyHistory());
        $this->assertSame(RecoveryBarrierState::Blocked, $journal->recoveryBarrier());

        $this->assertTrue($this->journal()
            ->recordNoEffectItemResolution($runId, $itemId, $this->reconciliationUuid(2), $this->reconciliationMoment(), $context)->replayed);
        $this->assertReconciliationError(
            ReconciliationError::ReplayConflict,
            fn () => $this->journal()->recordNoEffectItemResolution($runId, $itemId, $this->reconciliationUuid(3), $this->reconciliationMoment(), $context),
        );
        $this->assertReconciliationError(
            ReconciliationError::ReplayConflict,
            fn () => $this->journal()->recordForwardItemResolution($runId, $itemId, $this->reconciliationUuid(4),
                $this->forwardEvidenceFor($itemId), $context),
            'a closed item may not be forward accepted afterwards',
        );
        $this->assertSame(2, DB::table(self::EVENTS)->count());
    }

    #[DataProvider('noEffectHistories')]
    public function test_no_effect_accepts_journal_proof_of_absent_storage_effect(string $scenario): void
    {
        [$journal, $runId, $itemId] = $this->candidatePlan();
        $this->applyScenario($journal, $itemId, $scenario);
        $context = $this->reconciliationContext();
        $this->journal()->beginRunReconciliation($runId, $this->reconciliationUuid(1), $this->reconciliationMoment(), $context);
        $history = $this->applyHistory();

        $record = $this->journal()->recordNoEffectItemResolution($runId, $itemId, $this->reconciliationUuid(2), $this->reconciliationMoment(), $context);

        $this->assertSame(ItemReconciliationResult::ClosedNoEffect, $record->itemResult);
        $this->assertSame('closed_no_effect',
            DB::table('media_backfill_items')->where('id', $itemId)->value('reconciliation_result'));
        $this->assertSame(0, DB::table('media_backfill_objects')->where('item_id', $itemId)
            ->whereNotNull('reconciliation_resolution')->count());
        $this->assertSame($history, $this->applyHistory());
    }

    public static function noEffectHistories(): array
    {
        return [
            'planned rows' => ['not_dispatched'],
            'rejected collision rows' => ['rejected'],
            'failed without write rows' => ['failed'],
            'mixed safe rows' => ['mixed_no_effect'],
        ];
    }

    public function test_no_effect_accepts_an_unfinished_item_without_planned_objects(): void
    {
        $journal = app(ApplyJournal::class);
        $runId = $journal->createApplyRun($this->identity(), $this->applySelection());
        $itemId = $journal->snapshot($runId, $this->excludedItem(7));
        $context = $this->reconciliationContext();
        $this->journal()->beginRunReconciliation($runId, $this->reconciliationUuid(1), $this->reconciliationMoment(), $context);

        $record = $this->journal()->recordNoEffectItemResolution($runId, $itemId, $this->reconciliationUuid(2), $this->reconciliationMoment(), $context);

        $this->assertSame(ItemReconciliationResult::ClosedNoEffect, $record->itemResult);
        $this->assertSame(0, $record->objectsResolved);
        $this->assertStringContainsString('"objects":[]',
            DB::table(self::EVENTS)->where('event_id', $this->reconciliationUuid(2))->value('evidence_json'));
        $this->assertSame('inspected', DB::table('media_backfill_items')->where('id', $itemId)->value('phase'));
    }

    #[DataProvider('noEffectRejections')]
    public function test_no_effect_rejects_any_history_that_could_have_created_an_object(string $scenario): void
    {
        [$journal, $runId, $itemId] = $this->candidatePlan();
        $this->applyScenario($journal, $itemId, $scenario);
        $context = $this->reconciliationContext();
        $this->journal()->beginRunReconciliation($runId, $this->reconciliationUuid(1), $this->reconciliationMoment(), $context);
        $history = $this->applyHistory();

        $this->assertReconciliationError(
            ReconciliationError::IllegalResolution,
            fn () => $this->journal()->recordNoEffectItemResolution($runId, $itemId, $this->reconciliationUuid(2), $this->reconciliationMoment(), $context),
        );
        $this->assertNull(DB::table('media_backfill_items')->where('id', $itemId)->value('reconciliation_result'));
        $this->assertSame(1, DB::table(self::EVENTS)->count());
        $this->assertSame($history, $this->applyHistory());
    }

    public static function noEffectRejections(): array
    {
        return [
            'intent' => ['intent'],
            'unknown' => ['unknown'],
            'created receipt' => ['created'],
            'cleanup pending' => ['cleanup_pending'],
            'cleanup failed' => ['cleanup_failed'],
            'cleanup unknown' => ['cleanup_unknown'],
            'cleanup deleted' => ['cleanup_deleted'],
            'finished item' => ['finished_skipped'],
            'finished publication unknown' => ['finished_publication_unknown'],
        ];
    }

    public function test_no_effect_rejects_a_tampered_unfinished_item(): void
    {
        [, $runId, $itemId] = $this->candidatePlan();
        $context = $this->reconciliationContext();
        $this->journal()->beginRunReconciliation($runId, $this->reconciliationUuid(1), $this->reconciliationMoment(), $context);
        DB::table('media_backfill_items')->where('id', $itemId)->update(['apply_result' => ApplyResult::Skipped->value]);

        $this->assertReconciliationError(
            ReconciliationError::InconsistentJournal,
            fn () => $this->journal()->recordNoEffectItemResolution($runId, $itemId, $this->reconciliationUuid(2), $this->reconciliationMoment(), $context),
        );
        $this->assertNull(DB::table('media_backfill_items')->where('id', $itemId)->value('reconciliation_result'));
    }

    #[DataProvider('injectionPoints')]
    public function test_an_injected_failure_rolls_back_the_event_and_every_projection(string $needle): void
    {
        [, $runId, $itemId] = $this->candidatePlan();
        $context = $this->reconciliationContext();
        $this->journal()->beginRunReconciliation($runId, $this->reconciliationUuid(1), $this->reconciliationMoment(), $context);
        $evidence = $this->forwardEvidenceFor($itemId);
        $before = $this->journalSnapshot();
        $this->failOnce($needle);

        $this->assertReconciliationError(
            ReconciliationError::JournalUnavailable,
            fn () => $this->journal()->recordForwardItemResolution($runId, $itemId, $this->reconciliationUuid(2), $evidence, $context),
        );
        $this->assertSame($before, $this->journalSnapshot());
        $this->assertSame(0, DB::transactionLevel());
    }

    public static function injectionPoints(): array
    {
        return [
            'after the event insert' => ['insert into `media_backfill_reconciliation_events`'],
            'during the object projection' => ['update `media_backfill_objects`'],
            'before the item projection' => ['update `media_backfill_items`'],
        ];
    }

    public function test_an_ambient_caller_transaction_is_rejected(): void
    {
        [, $runId] = $this->candidatePlan();
        $context = $this->reconciliationContext();
        DB::beginTransaction();
        try {
            $this->assertReconciliationError(
                ReconciliationError::AmbientTransaction,
                fn () => $this->journal()->beginRunReconciliation($runId, $this->reconciliationUuid(1), $this->reconciliationMoment(), $context),
            );
        } finally {
            DB::rollBack();
        }
        $this->assertSame(0, DB::table(self::EVENTS)->count());
    }

    public function test_lock_loss_before_commit_rolls_back_the_whole_resolution(): void
    {
        [, $runId, $itemId] = $this->candidatePlan();
        $context = $this->reconciliationContext();
        $this->journal()->beginRunReconciliation($runId, $this->reconciliationUuid(1), $this->reconciliationMoment(), $context);
        $evidence = $this->forwardEvidenceFor($itemId);
        $before = $this->journalSnapshot();
        $this->killLockOnce('insert into `media_backfill_reconciliation_events`');

        $this->assertReconciliationError(
            ReconciliationError::LockLost,
            fn () => $this->journal()->recordForwardItemResolution($runId, $itemId, $this->reconciliationUuid(2), $evidence, $context),
        );
        $this->assertSame($before, $this->journalSnapshot());
    }

    public function test_forward_acceptance_never_clears_an_unresolved_write_blocker(): void
    {
        [$journal, $runId, $itemId] = $this->candidatePlan();
        $this->applyScenario($journal, $itemId, 'finished_publication_unknown');
        $journal->finishRun($runId, RunState::Interrupted);
        $context = $this->reconciliationContext();
        $this->journal()->beginRunReconciliation($runId, $this->reconciliationUuid(1), $this->reconciliationMoment(), $context);

        $this->journal()->recordForwardItemResolution($runId, $itemId, $this->reconciliationUuid(2), $this->forwardEvidenceFor($itemId), $context);

        $this->assertForwardProjected($itemId, $this->reconciliationUuid(2));
        $this->assertSame(RecoveryBarrierState::Blocked, $journal->recoveryBarrier());
        $this->assertSame('unknown', DB::table('media_backfill_objects')->where('item_id', $itemId)->value('write_state'));
    }

    public function test_no_effect_never_clears_the_unfinished_item_blocker(): void
    {
        [$journal, $runId, $itemId] = $this->candidatePlan();
        $journal->finishRun($runId, RunState::Interrupted);
        $context = $this->reconciliationContext();
        $this->journal()->beginRunReconciliation($runId, $this->reconciliationUuid(1), $this->reconciliationMoment(), $context);

        $this->journal()->recordNoEffectItemResolution($runId, $itemId, $this->reconciliationUuid(2), $this->reconciliationMoment(), $context);

        $this->assertSame('closed_no_effect',
            DB::table('media_backfill_items')->where('id', $itemId)->value('reconciliation_result'));
        $this->assertSame('revalidated', DB::table('media_backfill_items')->where('id', $itemId)->value('phase'));
        $this->assertSame(RecoveryBarrierState::Blocked, $journal->recoveryBarrier());
    }

    public function test_recovery_barrier_still_uses_exactly_its_four_historical_predicates(): void
    {
        $method = new ReflectionMethod(ApplyJournal::class, 'recoveryBarrier');
        $lines = file($method->getFileName());
        $source = implode('', array_slice($lines, $method->getStartLine() - 1,
            $method->getEndLine() - $method->getStartLine() + 1));

        $this->assertStringContainsString("where('state', RunState::Active->value)", $source);
        $this->assertStringContainsString("where('phase', '!=', ItemPhase::Finished->value)", $source);
        $this->assertStringContainsString("whereIn('write_state', [ObjectWriteState::Intent->value, ObjectWriteState::Unknown->value])", $source);
        $this->assertStringContainsString("orWhereIn('cleanup_state', [CleanupState::Pending->value, CleanupState::Failed->value, CleanupState::Unknown->value])", $source);
        $this->assertStringNotContainsString('reconciliation', $source);
        $this->assertStringNotContainsString('Storage', $source);
    }

    public function test_the_mutation_repository_has_no_storage_io_cleanup_or_operational_wiring(): void
    {
        $source = file_get_contents(app_path('Services/Media/Backfill/Reconciliation/ReconciliationJournal.php'));
        foreach (['Storage::', 'Filesystem', 'ExactObjectObserver', 'ExclusiveObjectCreator', 'JournaledObjectWriter',
            'ApplyItemPublisher', 'MariaDbBackfillLock', 'ResponsiveBackfillApply', 'TargetObject', 'disk(', 'put(',
            'delete(', 'readStream', 'fileExists', 'unlink', 'listContents', 'GET_LOCK', 'RELEASE_LOCK'] as $forbidden) {
            $this->assertStringNotContainsString($forbidden, $source);
        }
        $dependencies = array_map(
            static fn (\ReflectionParameter $parameter): string => (string) $parameter->getType(),
            (new ReflectionClass(ReconciliationJournal::class))->getConstructor()->getParameters(),
        );
        $this->assertSame([
            'Illuminate\\Database\\DatabaseManager',
            'App\\Services\\Media\\Backfill\\Safety\\ApplyMaintenanceGuard',
            'App\\Services\\Media\\Backfill\\Reconciliation\\ObjectEvidenceClassifier',
            'App\\Services\\Media\\Backfill\\Reconciliation\\CandidateManifestReader',
            'App\\Services\\Media\\Backfill\\Reconciliation\\ReconciliationEventValidator',
        ], $dependencies);
        foreach (['closeReconciledRun', 'recordCleanupResolution', 'recordForwardObjectResolution',
            'confirmAbsence', 'advanceCheckpoint', 'finishRun', 'finishItem', 'updateCleanup', 'recordReceipt',
            'commitIntent', 'recoveryBarrier'] as $method) {
            $this->assertFalse(method_exists(ReconciliationJournal::class, $method));
            $this->assertStringNotContainsString($method.'(', $source);
        }
        $public = array_values(array_diff(array_map(
            static fn (ReflectionMethod $method): string => $method->getName(),
            (new ReflectionClass(ReconciliationJournal::class))->getMethods(ReflectionMethod::IS_PUBLIC),
        ), ['__construct']));
        sort($public);
        $this->assertSame(['beginRunReconciliation', 'recordBlockedAttempt', 'recordForwardItemResolution',
            'recordNoEffectItemResolution'], $public);

        foreach ([app_path('Console'), app_path('Http'), app_path('Providers'), app_path('Jobs'),
            base_path('routes'), base_path('bootstrap')] as $root) {
            if (! is_dir($root)) {
                continue;
            }
            foreach (File::allFiles($root) as $file) {
                $this->assertStringNotContainsString('ReconciliationJournal', file_get_contents($file->getPathname()),
                    $file->getRelativePathname());
            }
        }
    }

    #[DataProvider('invalidAttemptStarts')]
    public function test_a_resolution_requires_a_semantically_valid_attempt_start(string $case, string $error): void
    {
        [, $runId, $itemId] = $this->candidatePlan();
        $context = $this->reconciliationContext();
        $this->journal()->beginRunReconciliation($runId, $this->reconciliationUuid(1),
            $this->reconciliationMoment(), $context);
        $evidence = $this->forwardEvidenceFor($itemId);
        $this->corruptAttemptStart($case, $runId, $itemId);
        $expected = ReconciliationError::from($error);
        $before = $this->journalSnapshot();

        foreach ([
            'forward' => fn () => $this->journal()->recordForwardItemResolution($runId, $itemId,
                $this->reconciliationUuid(2), $evidence, $context),
            'no effect' => fn () => $this->journal()->recordNoEffectItemResolution($runId, $itemId,
                $this->reconciliationUuid(3), $this->reconciliationMoment(), $context),
            'blocked' => fn () => $this->journal()->recordBlockedAttempt($runId, $itemId,
                $this->reconciliationUuid(4), ReconciliationBlockReason::EvidenceMismatch,
                $this->reconciliationMoment(), $context),
        ] as $operation => $callback) {
            $this->assertReconciliationError($expected, $callback, $case.' / '.$operation);
        }
        $this->assertSame($before, $this->journalSnapshot());
    }

    public static function invalidAttemptStarts(): array
    {
        return [
            'item scoped start' => ['item scoped', 'inconsistent_journal'],
            'start of another run' => ['foreign run', 'replay_conflict'],
            'start retyped as another event' => ['wrong type', 'illegal_resolution'],
            'start with a broken evidence hash' => ['bad evidence hash', 'inconsistent_journal'],
            'start with non object evidence' => ['non object evidence', 'inconsistent_journal'],
            'start with an unexpected envelope key' => ['unexpected envelope key', 'inconsistent_journal'],
            'start with a discordant envelope kind' => ['wrong envelope kind', 'inconsistent_journal'],
            'start with another storage identity' => ['wrong storage identity', 'inconsistent_journal'],
            'start with another backend mode' => ['wrong backend mode', 'inconsistent_journal'],
            'start with a malformed code revision' => ['malformed code revision', 'inconsistent_journal'],
            'start with a discordant code revision' => ['discordant code revision', 'inconsistent_journal'],
            'start with an unsupported evidence version' => ['unsupported evidence version', 'inconsistent_journal'],
            'duplicated start' => ['duplicate start', 'inconsistent_journal'],
            'missing start' => ['missing start', 'illegal_resolution'],
        ];
    }

    public function test_a_run_reconciliation_pointer_is_out_of_reach_for_this_block(): void
    {
        [, $runId, $itemId] = $this->candidatePlan();
        $context = $this->reconciliationContext();
        $this->journal()->beginRunReconciliation($runId, $this->reconciliationUuid(1),
            $this->reconciliationMoment(), $context);
        $evidence = $this->forwardEvidenceFor($itemId);
        // Only D2-B3 may close a run: even a real event of this run is not an acceptable projection.
        DB::table('media_backfill_runs')->where('run_id', $runId)
            ->update(['reconciliation_event_id' => $this->reconciliationUuid(1)]);
        $before = $this->journalSnapshot();

        foreach ([
            'attempt' => fn () => $this->journal()->beginRunReconciliation($runId, $this->reconciliationUuid(2),
                $this->reconciliationMoment(), $this->reconciliationContext(93)),
            'blocked' => fn () => $this->journal()->recordBlockedAttempt($runId, $itemId,
                $this->reconciliationUuid(3), ReconciliationBlockReason::ResolutionConflict,
                $this->reconciliationMoment(), $context),
            'forward' => fn () => $this->journal()->recordForwardItemResolution($runId, $itemId,
                $this->reconciliationUuid(4), $evidence, $context),
            'no effect' => fn () => $this->journal()->recordNoEffectItemResolution($runId, $itemId,
                $this->reconciliationUuid(5), $this->reconciliationMoment(), $context),
        ] as $operation => $callback) {
            $this->assertReconciliationError(ReconciliationError::IllegalResolution, $callback, $operation);
        }
        $this->assertSame($before, $this->journalSnapshot());
    }

    #[DataProvider('tamperedCandidateMetadata')]
    public function test_item_candidate_metadata_must_match_its_preflight_classification(string $case): void
    {
        [, $runId, $itemId] = $this->candidatePlan();
        $context = $this->reconciliationContext();
        $this->journal()->beginRunReconciliation($runId, $this->reconciliationUuid(1),
            $this->reconciliationMoment(), $context);
        $evidence = $this->forwardEvidenceFor($itemId);
        $this->tamperCandidate($case, $itemId);
        $before = $this->journalSnapshot();

        foreach ([
            'no effect' => fn () => $this->journal()->recordNoEffectItemResolution($runId, $itemId,
                $this->reconciliationUuid(2), $this->reconciliationMoment(), $context),
            'forward' => fn () => $this->journal()->recordForwardItemResolution($runId, $itemId,
                $this->reconciliationUuid(3), $evidence, $context),
        ] as $operation => $callback) {
            $this->assertReconciliationError(ReconciliationError::InconsistentJournal, $callback, $case.' / '.$operation);
        }
        $this->assertSame($before, $this->journalSnapshot());
    }

    public static function tamperedCandidateMetadata(): array
    {
        return [
            'candidate stripped from a backfillable item' => ['candidate removed'],
            'candidate hash mismatch' => ['candidate hash'],
            'malformed candidate manifest' => ['malformed candidate'],
            'master key hash mismatch' => ['master hash'],
            'manifest key removed' => ['manifest key'],
            'source hash removed' => ['source hash'],
            'non candidate carrying candidate metadata' => ['non candidate candidate'],
            'non candidate carrying planned objects' => ['non candidate objects'],
        ];
    }

    public function test_no_effect_accepts_a_backfillable_item_that_crashed_before_planning_objects(): void
    {
        $journal = app(ApplyJournal::class);
        $runId = $journal->createApplyRun($this->identity(), $this->applySelection());
        $itemId = $journal->snapshot($runId, $this->preflight(123));
        $context = $this->reconciliationContext();
        $this->journal()->beginRunReconciliation($runId, $this->reconciliationUuid(1),
            $this->reconciliationMoment(), $context);

        $record = $this->journal()->recordNoEffectItemResolution($runId, $itemId, $this->reconciliationUuid(2),
            $this->reconciliationMoment(), $context);

        $this->assertSame(ItemReconciliationResult::ClosedNoEffect, $record->itemResult);
        $this->assertSame(0, $record->objectsResolved);
        $this->assertSame('inspected', DB::table('media_backfill_items')->where('id', $itemId)->value('phase'));
        $this->assertSame('legacy_backfillable',
            DB::table('media_backfill_items')->where('id', $itemId)->value('preflight_classification'));
    }

    private function corruptAttemptStart(string $case, string $runId, int $itemId): void
    {
        $startId = $this->reconciliationUuid(1);
        $start = DB::table(self::EVENTS)->where('event_id', $startId)->first();
        $envelope = json_decode($start->evidence_json, true);
        if ($case === 'missing start') {
            DB::table(self::EVENTS)->where('event_id', $startId)->delete();

            return;
        }
        if ($case === 'duplicate start') {
            $duplicate = (array) $start;
            $duplicate['event_id'] = $this->reconciliationUuid(9);
            DB::table(self::EVENTS)->insert($duplicate);

            return;
        }
        $update = match ($case) {
            'item scoped' => ['item_id' => $itemId],
            'foreign run' => ['run_id' => app(ApplyJournal::class)
                ->createApplyRun($this->identity(), $this->applySelection())],
            'wrong type' => ['event_type' => ReconciliationEventType::AttemptBlocked->value],
            'bad evidence hash' => ['evidence_sha256' => str_repeat('0', 64)],
            // MariaDB enforces json_valid() on the column, so the reachable corruption is a
            // syntactically valid payload that is not a v1 envelope object.
            'non object evidence' => $this->evidencePayload('"not-an-envelope"'),
            'unexpected envelope key' => $this->evidencePayload(json_encode($envelope + ['extra' => true])),
            'wrong envelope kind' => $this->evidencePayload(json_encode(
                array_replace($envelope, ['kind' => ReconciliationEventType::AttemptBlocked->value]))),
            'wrong storage identity' => ['storage_identity_hash' => str_repeat('b', 64)],
            'wrong backend mode' => ['backend_mode' => ReconciliationBackendMode::S3->value],
            'malformed code revision' => ['code_revision' => 'not-a-revision'],
            'discordant code revision' => ['code_revision' => str_repeat('b', 40)],
            'unsupported evidence version' => ['evidence_version' => 2],
        };
        DB::table(self::EVENTS)->where('event_id', $startId)->update($update);
    }

    /** @return array{evidence_json: string, evidence_sha256: string} */
    private function evidencePayload(string $json): array
    {
        return ['evidence_json' => $json, 'evidence_sha256' => hash('sha256', $json)];
    }

    private function tamperCandidate(string $case, int $itemId): void
    {
        $item = DB::table('media_backfill_items')->where('id', $itemId)->first();
        $broken = '{"schema_version":2}';
        $update = match ($case) {
            'candidate removed' => ['candidate_manifest_json' => null, 'candidate_manifest_sha256' => null],
            'candidate hash' => ['candidate_manifest_sha256' => str_repeat('c', 64)],
            'malformed candidate' => ['candidate_manifest_json' => $broken,
                'candidate_manifest_sha256' => hash('sha256', $broken)],
            'master hash' => ['master_key_hash' => str_repeat('c', 64)],
            'manifest key' => ['manifest_key' => null],
            'source hash' => ['source_sha256' => null],
            'non candidate candidate' => ['preflight_classification' => 'excluded_deleted'],
            'non candidate objects' => ['preflight_classification' => 'excluded_deleted',
                'candidate_manifest_json' => null, 'candidate_manifest_sha256' => null, 'source_sha256' => null,
                'phase' => 'inspected', 'revalidated_at' => null],
        };
        if ($case === 'candidate removed') {
            DB::table('media_backfill_objects')->where('item_id', $itemId)->delete();
        }
        DB::table('media_backfill_items')->where('id', $item->id)->update($update);
    }

    private function journal(): ReconciliationJournal
    {
        return app(ReconciliationJournal::class);
    }

    private function applyScenario(ApplyJournal $journal, int $itemId, string $scenario): void
    {
        $ids = $this->objectIds($itemId);
        $first = $ids[0];
        match ($scenario) {
            'not_dispatched' => null,
            'intent' => $journal->commitIntent($first),
            'unknown' => $this->receipt($journal, $first, new CreateReceipt(CreateState::Unknown)),
            'created' => $this->receipt($journal, $first,
                new CreateReceipt(CreateState::Created, 'private-etag', 'private-version')),
            'rejected' => $this->receipt($journal, $first, new CreateReceipt(CreateState::Rejected)),
            'failed' => $this->receipt($journal, $first, new CreateReceipt(CreateState::Failed)),
            'mixed_no_effect' => $this->mixedNoEffect($journal, $ids),
            'cleanup_pending' => $this->cleanup($journal, $first, [CleanupState::Pending]),
            'cleanup_failed' => $this->cleanup($journal, $first, [CleanupState::Pending, CleanupState::Failed]),
            'cleanup_unknown' => $this->cleanup($journal, $first, [CleanupState::Pending, CleanupState::Unknown]),
            'cleanup_deleted' => $this->cleanup($journal, $first, [CleanupState::Pending, CleanupState::Deleted]),
            'finished_skipped' => $journal->finishItem($itemId, ApplyResult::Skipped),
            'finished_publication_unknown' => $this->finishUnknown($journal, $itemId, $ids),
            'finished_cleanup_incomplete' => $this->finishCleanupIncomplete($journal, $itemId, $first),
        };
    }

    private function receipt(ApplyJournal $journal, int $objectId, CreateReceipt $receipt): void
    {
        $journal->commitIntent($objectId);
        $journal->recordReceipt($objectId, $receipt);
    }

    /** @param list<CleanupState> $states */
    private function cleanup(ApplyJournal $journal, int $objectId, array $states): void
    {
        $this->receipt($journal, $objectId, new CreateReceipt(CreateState::Created, 'private-etag', 'private-version'));
        foreach ($states as $state) {
            $journal->updateCleanup($objectId, $state);
        }
    }

    /** @param list<int> $ids */
    private function mixedNoEffect(ApplyJournal $journal, array $ids): void
    {
        $this->receipt($journal, $ids[0], new CreateReceipt(CreateState::Rejected));
        $this->receipt($journal, (int) end($ids), new CreateReceipt(CreateState::Failed));
    }

    /** @param list<int> $ids */
    private function finishUnknown(ApplyJournal $journal, int $itemId, array $ids): void
    {
        foreach ($ids as $id) {
            $this->receipt($journal, $id, new CreateReceipt(CreateState::Unknown));
        }
        $journal->finishItem($itemId, ApplyResult::PublicationUnknown);
    }

    private function finishCleanupIncomplete(ApplyJournal $journal, int $itemId, int $objectId): void
    {
        $this->cleanup($journal, $objectId, [CleanupState::Pending]);
        $journal->finishItem($itemId, ApplyResult::FailedCleanupIncomplete);
    }

    private function assertForwardProjected(int $itemId, string $eventId): void
    {
        $item = DB::table('media_backfill_items')->where('id', $itemId)->first();
        $this->assertSame('forward_accepted', $item->reconciliation_result);
        $this->assertSame($eventId, $item->reconciliation_event_id);
        foreach (DB::table('media_backfill_objects')->where('item_id', $itemId)->get() as $row) {
            $this->assertSame('forward_retained', $row->reconciliation_resolution);
            $this->assertSame($eventId, $row->reconciliation_event_id);
        }
    }

    /** Every APPLY column, with the separate reconciliation dimension removed. */
    private function applyHistory(): array
    {
        $strip = static function (array $row): array {
            foreach (array_keys($row) as $column) {
                if (str_starts_with($column, 'reconciliation_')) {
                    unset($row[$column]);
                }
            }

            return $row;
        };

        return [
            'runs' => DB::table('media_backfill_runs')->orderBy('run_id')->get()->map(fn ($row) => $strip((array) $row))->all(),
            'items' => DB::table('media_backfill_items')->orderBy('id')->get()->map(fn ($row) => $strip((array) $row))->all(),
            'objects' => DB::table('media_backfill_objects')->orderBy('id')->get()->map(fn ($row) => $strip((array) $row))->all(),
        ];
    }

    private function journalSnapshot(): array
    {
        return [
            'runs' => DB::table('media_backfill_runs')->orderBy('run_id')->get()->map(fn ($row) => (array) $row)->all(),
            'items' => DB::table('media_backfill_items')->orderBy('id')->get()->map(fn ($row) => (array) $row)->all(),
            'objects' => DB::table('media_backfill_objects')->orderBy('id')->get()->map(fn ($row) => (array) $row)->all(),
            'events' => DB::table(self::EVENTS)->orderBy('event_id')->get()->map(fn ($row) => (array) $row)->all(),
        ];
    }

    private function failOnce(string $needle): void
    {
        $fired = false;
        DB::listen(function (QueryExecuted $query) use ($needle, &$fired) {
            if (! $fired && str_contains($query->sql, $needle)) {
                $fired = true;
                throw new RuntimeException('injected failure');
            }
        });
    }

    private function killLockOnce(string $needle): void
    {
        $fired = false;
        $connectionId = (int) $this->backfillLock()->connectionId;
        DB::listen(function (QueryExecuted $query) use ($needle, &$fired, $connectionId) {
            if (! $fired && str_contains($query->sql, $needle)) {
                $fired = true;
                DB::unprepared('KILL CONNECTION '.$connectionId);
            }
        });
    }

    private function assertReconciliationError(ReconciliationError $reason, callable $callback, string $context = ''): void
    {
        try {
            $callback();
            $this->fail('Expected a typed reconciliation failure. '.$context);
        } catch (ReconciliationException $error) {
            $this->assertSame($reason, $error->reason, $context);
            $this->assertSame($reason->value, $error->getMessage());
            $this->assertNull($error->getPrevious());
        }
    }
}
