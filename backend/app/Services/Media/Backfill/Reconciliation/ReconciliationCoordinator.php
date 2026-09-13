<?php

namespace App\Services\Media\Backfill\Reconciliation;

use App\Services\Media\Backfill\Safety\AdvisoryLockHandle;
use App\Services\Media\Backfill\Safety\ApplyJournal;
use App\Services\Media\Backfill\Safety\ApplyMaintenanceGuard;
use App\Services\Media\Backfill\Safety\BackfillSafetyException;
use App\Services\Media\Backfill\Safety\LockAcquireState;
use App\Services\Media\Backfill\Safety\MariaDbBackfillLock;
use App\Services\Media\Backfill\Safety\RecoveryBarrierState;
use App\Services\Media\Backfill\Safety\RunState;
use App\Services\Media\Backfill\Safety\SafetyError;
use App\Services\Media\Backfill\Safety\StorageIdentity;
use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;
use Illuminate\Database\Connection;
use Illuminate\Database\DatabaseManager;
use Illuminate\Support\Str;
use stdClass;
use Throwable;

/** Internal C2 coordinator for one complete durable run. It has deliberately no operational caller. */
class ReconciliationCoordinator
{
    private const MAX_ITEMS = 1000;

    public function __construct(
        private readonly ApplyMaintenanceGuard $maintenance,
        private readonly DatabaseManager $database,
        private readonly MariaDbBackfillLock $locks,
        private readonly StorageObservationCapability $capability,
        private readonly ManagedMediaWriterFreezeGuard $writerFreeze,
        private readonly ReconciliationStateValidator $states,
        private readonly ForwardItemEvidenceBuilder $forwardEvidence,
        private readonly ReconciliationJournal $reconciliationJournal,
        private readonly ApplyJournal $applyJournal,
    ) {}

    public function run(ReconciliationInvocation $invocation): ReconciliationReport
    {
        try {
            $this->maintenance->assertAllowed();
        } catch (BackfillSafetyException $error) {
            return $this->emptyReport(
                $invocation,
                $error->reason === SafetyError::MaintenanceRequired
                    ? ReconciliationOutcome::MaintenanceRequired
                    : ReconciliationOutcome::SafetyFailure,
            );
        } catch (Throwable) {
            return $this->emptyReport($invocation, ReconciliationOutcome::SafetyFailure);
        }

        try {
            $this->assertConnection();
            $identity = $this->currentIdentity();
            $backendMode = ReconciliationBackendMode::current();
            $this->capability->disk($identity, $backendMode);
        } catch (Throwable) {
            return $this->emptyReport($invocation, ReconciliationOutcome::SafetyFailure);
        }

        try {
            $acquisition = $this->locks->acquire($identity);
        } catch (Throwable) {
            return $this->emptyReport($invocation, ReconciliationOutcome::LockAcquireFailed);
        }
        $lock = $acquisition->handle;
        if ($acquisition->state === LockAcquireState::Busy && $lock === null) {
            return $this->emptyReport($invocation, ReconciliationOutcome::LockBusy);
        }
        if ($acquisition->state !== LockAcquireState::Acquired || ! $lock instanceof AdvisoryLockHandle) {
            $report = $this->emptyReport($invocation, ReconciliationOutcome::LockAcquireFailed);

            return $lock === null ? $report : $this->release($lock, $report);
        }

        try {
            // Ownership is proved as the first action after acquisition and again inside every gate.
            $lock->assertOwned();
            $report = $this->runLocked($invocation, $identity, $backendMode, $lock);
        } catch (Throwable) {
            $report = $this->emptyReport($invocation, ReconciliationOutcome::SafetyFailure);
        }

        return $this->release($lock, $report);
    }

    private function runLocked(
        ReconciliationInvocation $invocation,
        StorageIdentity $identity,
        ReconciliationBackendMode $backendMode,
        AdvisoryLockHandle $lock,
    ): ReconciliationReport {
        $attemptId = null;
        $mutationAttempted = false;
        $durableProgress = false;
        $traversed = 0;
        $skipped = 0;
        $resolvedForward = 0;
        $resolvedNoEffect = 0;
        $closureAppended = false;

        try {
            $this->assertOperational($identity, $backendMode, $lock);
            [$run, $items, $objects] = $this->loadRunSnapshot($invocation->runId);
            if (! is_string($run->storage_identity_hash ?? null)
                || preg_match('/\A[0-9a-f]{64}\z/D', $run->storage_identity_hash) !== 1) {
                throw new ReconciliationException(ReconciliationError::InconsistentJournal);
            }
            if (! hash_equals($identity->hash, $run->storage_identity_hash)) {
                throw new ReconciliationException(ReconciliationError::IdentityMismatch);
            }

            if (($run->reconciliation_event_id ?? null) !== null) {
                if (! $this->states->runHistoryIsValid($run, $items, $objects)
                    || ! $this->states->runClosureProjectionIsValid($run)) {
                    return $this->report(
                        ReconciliationOutcome::SafetyFailure,
                        $invocation,
                        null,
                        false,
                        0,
                        0,
                        0,
                        0,
                        ReconciliationBlockReason::InconsistentJournal,
                        false,
                        $this->runState($run),
                        null,
                        false,
                    );
                }
                $this->assertOperational($identity, $backendMode, $lock);
                [$finalRun, $finalItems, $finalObjects] = $this->loadRunSnapshot($invocation->runId);
                if (! $this->states->runHistoryIsValid($finalRun, $finalItems, $finalObjects)
                    || ! $this->states->runClosureProjectionIsValid($finalRun)) {
                    throw new ReconciliationException(ReconciliationError::InconsistentJournal);
                }

                $barrier = $this->applyJournal->recoveryBarrier();
                $this->assertOperational($identity, $backendMode, $lock);

                return $this->report(
                    ReconciliationOutcome::AlreadyClosed,
                    $invocation,
                    null,
                    true,
                    0,
                    0,
                    0,
                    0,
                    null,
                    false,
                    RunState::Interrupted,
                    $barrier,
                    false,
                );
            }

            $historyValid = $this->states->runHistoryIsValid($run, $items, $objects);
            $complete = $this->states->evaluate($run, $items, $objects);
            if (! $historyValid || $complete === null) {
                return $this->report(
                    ReconciliationOutcome::SafetyFailure,
                    $invocation,
                    null,
                    false,
                    0,
                    0,
                    0,
                    0,
                    ReconciliationBlockReason::InconsistentJournal,
                    false,
                    $this->runState($run),
                    null,
                    false,
                );
            }
            $state = $this->runState($run);
            if ($complete['resolved'] && in_array($state, [
                RunState::Completed,
                RunState::Failed,
                RunState::Interrupted,
            ], true)) {
                return $this->terminalNoOpReport($invocation, $identity, $backendMode, $lock);
            }

            $initialItemIds = array_map(static fn (stdClass $item): int => (int) $item->id, $items);
            $attemptId = $this->uuid();
            $context = new ReconciliationContext($attemptId, $lock, $identity, $backendMode);
            $this->assertOperational($identity, $backendMode, $lock);
            $this->assertRunStillMatches($invocation->runId, $initialItemIds);
            $mutationAttempted = true;
            $this->reconciliationJournal->beginRunReconciliation(
                $invocation->runId,
                $this->uuid(),
                $this->moment(),
                $context,
            );
            $mutationAttempted = false;
            $durableProgress = true;

            foreach ($initialItemIds as $itemId) {
                $traversed++;
                $this->assertOperational($identity, $backendMode, $lock);
                [$currentRun, $item, $itemObjects] = $this->loadItemSnapshot($invocation->runId, $itemId);
                $analysis = $this->states->analyzeItem($currentRun, $item, $itemObjects);
                if ($analysis->state === ReconciliationItemOperationalState::Inconsistent) {
                    return $this->blocked(
                        $invocation,
                        $context,
                        $itemId,
                        ReconciliationBlockReason::InconsistentJournal,
                        $traversed,
                        $skipped,
                        $resolvedForward,
                        $resolvedNoEffect,
                        $durableProgress,
                    );
                }
                if ($analysis->hasCleanupAttention()
                    && $analysis->state !== ReconciliationItemOperationalState::ForwardCandidate) {
                    return $this->blocked(
                        $invocation,
                        $context,
                        $itemId,
                        ReconciliationBlockReason::ResolutionConflict,
                        $traversed,
                        $skipped,
                        $resolvedForward,
                        $resolvedNoEffect,
                        $durableProgress,
                    );
                }

                if (in_array($analysis->state, [
                    ReconciliationItemOperationalState::OrdinaryNonBlocking,
                    ReconciliationItemOperationalState::ResolvedForward,
                    ReconciliationItemOperationalState::ResolvedNoEffect,
                ], true)) {
                    $skipped++;

                    continue;
                }

                if ($analysis->state === ReconciliationItemOperationalState::NoEffectCandidate) {
                    $this->assertOperational($identity, $backendMode, $lock);
                    [$freshRun, $freshItem, $freshObjects] = $this->loadItemSnapshot($invocation->runId, $itemId);
                    $freshAnalysis = $this->states->analyzeItem($freshRun, $freshItem, $freshObjects);
                    if ($freshAnalysis->state !== ReconciliationItemOperationalState::NoEffectCandidate
                        || ! $freshAnalysis->noEffectEligible || $freshAnalysis->hasCleanupAttention()) {
                        return $this->blocked(
                            $invocation,
                            $context,
                            $itemId,
                            $freshAnalysis->state === ReconciliationItemOperationalState::Inconsistent
                                ? ReconciliationBlockReason::InconsistentJournal
                                : ReconciliationBlockReason::ResolutionConflict,
                            $traversed,
                            $skipped,
                            $resolvedForward,
                            $resolvedNoEffect,
                            $durableProgress,
                        );
                    }
                    $this->assertRunStillMatches($invocation->runId, $initialItemIds);
                    $mutationAttempted = true;
                    $this->reconciliationJournal->recordNoEffectItemResolution(
                        $invocation->runId,
                        $itemId,
                        $this->uuid(),
                        $this->moment(),
                        $context,
                    );
                    $mutationAttempted = false;
                    $durableProgress = true;
                    $resolvedNoEffect++;

                    continue;
                }

                if ($analysis->state === ReconciliationItemOperationalState::ForwardCandidate) {
                    try {
                        $this->writerFreeze->assertEstablished($backendMode);
                    } catch (OperationalEvidenceException $error) {
                        return $this->blocked(
                            $invocation,
                            $context,
                            $itemId,
                            $error->reason,
                            $traversed,
                            $skipped,
                            $resolvedForward,
                            $resolvedNoEffect,
                            $durableProgress,
                        );
                    }

                    // This is intentionally unreachable until the project gains a concrete writer
                    // freeze. Once it does, C2 already enforces first and final independent passes.
                    $firstPass = $this->forwardEvidence->build(
                        $invocation->runId,
                        $itemId,
                        $this->moment(),
                        $identity,
                        $backendMode,
                    );
                    if (! $firstPass->isAccepted()) {
                        return $this->blocked(
                            $invocation,
                            $context,
                            $itemId,
                            $firstPass->refusal ?? ReconciliationBlockReason::EvidenceMismatch,
                            $traversed,
                            $skipped,
                            $resolvedForward,
                            $resolvedNoEffect,
                            $durableProgress,
                        );
                    }
                    $this->assertOperational($identity, $backendMode, $lock);
                    [, $freshItem, $freshObjects] = $this->loadItemSnapshot($invocation->runId, $itemId);
                    $freshRun = $this->loadRun($invocation->runId);
                    $freshAnalysis = $this->states->analyzeItem($freshRun, $freshItem, $freshObjects);
                    if ($freshAnalysis->state !== ReconciliationItemOperationalState::ForwardCandidate) {
                        return $this->blocked(
                            $invocation,
                            $context,
                            $itemId,
                            $freshAnalysis->state === ReconciliationItemOperationalState::Inconsistent
                                ? ReconciliationBlockReason::InconsistentJournal
                                : ReconciliationBlockReason::ResolutionConflict,
                            $traversed,
                            $skipped,
                            $resolvedForward,
                            $resolvedNoEffect,
                            $durableProgress,
                        );
                    }
                    $finalPass = $this->forwardEvidence->build(
                        $invocation->runId,
                        $itemId,
                        $this->moment(),
                        $identity,
                        $backendMode,
                    );
                    if (! $finalPass->isAccepted()) {
                        return $this->blocked(
                            $invocation,
                            $context,
                            $itemId,
                            $finalPass->refusal ?? ReconciliationBlockReason::EvidenceMismatch,
                            $traversed,
                            $skipped,
                            $resolvedForward,
                            $resolvedNoEffect,
                            $durableProgress,
                        );
                    }
                    $this->assertOperational($identity, $backendMode, $lock);
                    $this->assertRunStillMatches($invocation->runId, $initialItemIds);
                    $mutationAttempted = true;
                    $this->reconciliationJournal->recordForwardItemResolution(
                        $invocation->runId,
                        $itemId,
                        $this->uuid(),
                        $finalPass->evidence,
                        $context,
                    );
                    $mutationAttempted = false;
                    $durableProgress = true;
                    $resolvedForward++;

                    if ($analysis->hasCleanupAttention()) {
                        return $this->blocked(
                            $invocation,
                            $context,
                            $itemId,
                            ReconciliationBlockReason::ResolutionConflict,
                            $traversed,
                            $skipped,
                            $resolvedForward,
                            $resolvedNoEffect,
                            $durableProgress,
                        );
                    }

                    continue;
                }

                return $this->blocked(
                    $invocation,
                    $context,
                    $itemId,
                    ReconciliationBlockReason::EvidenceMismatch,
                    $traversed,
                    $skipped,
                    $resolvedForward,
                    $resolvedNoEffect,
                    $durableProgress,
                );
            }

            $this->assertOperational($identity, $backendMode, $lock);
            [$runBeforeClosure, $itemsBeforeClosure, $objectsBeforeClosure] = $this->loadRunSnapshot($invocation->runId);
            if ($initialItemIds !== array_map(static fn (stdClass $item): int => (int) $item->id, $itemsBeforeClosure)
                || $traversed !== count($initialItemIds)) {
                return $this->blocked(
                    $invocation,
                    $context,
                    null,
                    ReconciliationBlockReason::InconsistentJournal,
                    $traversed,
                    $skipped,
                    $resolvedForward,
                    $resolvedNoEffect,
                    $durableProgress,
                );
            }
            $historyValid = $this->states->runHistoryIsValid(
                $runBeforeClosure,
                $itemsBeforeClosure,
                $objectsBeforeClosure,
            );
            $complete = $this->states->evaluate($runBeforeClosure, $itemsBeforeClosure, $objectsBeforeClosure);
            if (! $historyValid || $complete === null) {
                return $this->blocked(
                    $invocation,
                    $context,
                    null,
                    ReconciliationBlockReason::InconsistentJournal,
                    $traversed,
                    $skipped,
                    $resolvedForward,
                    $resolvedNoEffect,
                    $durableProgress,
                );
            }
            if (! $complete['resolved']) {
                return $this->blocked(
                    $invocation,
                    $context,
                    null,
                    ReconciliationBlockReason::ResolutionConflict,
                    $traversed,
                    $skipped,
                    $resolvedForward,
                    $resolvedNoEffect,
                    $durableProgress,
                );
            }

            $state = $this->runState($runBeforeClosure);
            if ($state === RunState::Active) {
                $this->assertOperational($identity, $backendMode, $lock);
                $mutationAttempted = true;
                $this->reconciliationJournal->closeRunAfterReconciliation(
                    $invocation->runId,
                    $this->uuid(),
                    $this->moment(),
                    $context,
                );
                $mutationAttempted = false;
                $durableProgress = true;
                $closureAppended = true;
            }

            return $this->finalReport(
                ReconciliationOutcome::Completed,
                $invocation,
                $attemptId,
                $traversed,
                $skipped,
                $resolvedForward,
                $resolvedNoEffect,
                null,
                $closureAppended,
                $durableProgress,
                $identity,
                $backendMode,
                $lock,
                true,
            );
        } catch (Throwable $error) {
            return $this->report(
                ReconciliationOutcome::SafetyFailure,
                $invocation,
                $attemptId,
                false,
                $traversed,
                $skipped,
                $resolvedForward,
                $resolvedNoEffect,
                $this->blockReason($error),
                $closureAppended,
                $this->safeRunState($invocation->runId),
                null,
                $mutationAttempted ? null : $durableProgress,
            );
        }
    }

    private function blocked(
        ReconciliationInvocation $invocation,
        ReconciliationContext $context,
        ?int $itemId,
        ReconciliationBlockReason $reason,
        int $traversed,
        int $skipped,
        int $resolvedForward,
        int $resolvedNoEffect,
        bool $durableProgress,
    ): ReconciliationReport {
        $mutationAttempted = false;
        try {
            $this->assertOperational($context->identity, $context->backendMode, $context->lock);
            $mutationAttempted = true;
            $this->reconciliationJournal->recordBlockedAttempt(
                $invocation->runId,
                $itemId,
                $this->uuid(),
                $reason,
                $this->moment(),
                $context,
            );
            $mutationAttempted = false;
            $durableProgress = true;

            return $this->finalReport(
                ReconciliationOutcome::Blocked,
                $invocation,
                $context->attemptId,
                $traversed,
                $skipped,
                $resolvedForward,
                $resolvedNoEffect,
                $reason,
                false,
                $durableProgress,
                $context->identity,
                $context->backendMode,
                $context->lock,
                false,
            );
        } catch (Throwable) {
            return $this->report(
                ReconciliationOutcome::SafetyFailure,
                $invocation,
                $context->attemptId,
                false,
                $traversed,
                $skipped,
                $resolvedForward,
                $resolvedNoEffect,
                $reason,
                false,
                $this->safeRunState($invocation->runId),
                null,
                $mutationAttempted ? null : $durableProgress,
            );
        }
    }

    private function finalReport(
        ReconciliationOutcome $outcome,
        ReconciliationInvocation $invocation,
        string $attemptId,
        int $traversed,
        int $skipped,
        int $resolvedForward,
        int $resolvedNoEffect,
        ?ReconciliationBlockReason $blocker,
        bool $closureAppended,
        bool $durableProgress,
        StorageIdentity $identity,
        ReconciliationBackendMode $backendMode,
        AdvisoryLockHandle $lock,
        bool $requireResolved,
    ): ReconciliationReport {
        $this->assertOperational($identity, $backendMode, $lock);
        [$run, $items, $objects] = $this->loadRunSnapshot($invocation->runId);
        $analysis = $this->states->evaluate($run, $items, $objects);
        $state = $this->runState($run);
        if (! $this->states->runHistoryIsValid($run, $items, $objects)
            || $analysis === null || ($requireResolved && ! $analysis['resolved'])) {
            throw new ReconciliationException(ReconciliationError::InconsistentJournal);
        }
        if ($closureAppended) {
            if ($state !== RunState::Interrupted || ! $this->states->runClosureProjectionIsValid($run)) {
                throw new ReconciliationException(ReconciliationError::InconsistentJournal);
            }
        } elseif (($run->reconciliation_event_id ?? null) !== null) {
            throw new ReconciliationException(ReconciliationError::IllegalResolution);
        }
        $barrier = $this->applyJournal->recoveryBarrier();
        $this->assertOperational($identity, $backendMode, $lock);

        return $this->report(
            $outcome,
            $invocation,
            $attemptId,
            false,
            $traversed,
            $skipped,
            $resolvedForward,
            $resolvedNoEffect,
            $blocker,
            $closureAppended,
            $state,
            $barrier,
            $durableProgress,
        );
    }

    private function terminalNoOpReport(
        ReconciliationInvocation $invocation,
        StorageIdentity $identity,
        ReconciliationBackendMode $backendMode,
        AdvisoryLockHandle $lock,
    ): ReconciliationReport {
        $this->assertOperational($identity, $backendMode, $lock);
        [$run, $items, $objects] = $this->loadRunSnapshot($invocation->runId);
        $state = $this->runState($run);
        $complete = $this->states->evaluate($run, $items, $objects);
        if (($run->reconciliation_event_id ?? null) !== null
            || ! in_array($state, [RunState::Completed, RunState::Failed, RunState::Interrupted], true)
            || ! $this->states->runHistoryIsValid($run, $items, $objects)
            || $complete === null
            || ! $complete['resolved']) {
            throw new ReconciliationException(ReconciliationError::InconsistentJournal);
        }
        $barrier = $this->applyJournal->recoveryBarrier();
        $this->assertOperational($identity, $backendMode, $lock);

        return $this->report(
            ReconciliationOutcome::NoReconciliationRequired,
            $invocation,
            null,
            true,
            0,
            0,
            0,
            0,
            null,
            false,
            $state,
            $barrier,
            false,
        );
    }

    private function assertOperational(
        StorageIdentity $identity,
        ReconciliationBackendMode $backendMode,
        AdvisoryLockHandle $lock,
    ): void {
        $lock->assertOwned();
        $this->maintenance->assertAllowed();
        $this->assertConnection();
        $current = $this->currentIdentity();
        if (! hash_equals($identity->hash, $current->hash)
            || ! hash_equals($identity->hash, $lock->identity->hash)
            || ReconciliationBackendMode::current() !== $backendMode) {
            throw new ReconciliationException(ReconciliationError::IdentityMismatch);
        }
        $this->capability->disk($identity, $backendMode);
        $lock->assertOwned();
    }

    private function assertRunStillMatches(string $runId, array $expectedItemIds): void
    {
        [$run, $items, $objects] = $this->loadRunSnapshot($runId);
        if (($run->reconciliation_event_id ?? null) !== null
            || $expectedItemIds !== array_map(static fn (stdClass $item): int => (int) $item->id, $items)
            || ! $this->states->runHistoryIsValid($run, $items, $objects)
            || $this->states->evaluate($run, $items, $objects) === null) {
            throw new ReconciliationException(ReconciliationError::InconsistentJournal);
        }
    }

    /** @return array{stdClass, list<stdClass>, list<stdClass>} */
    private function loadRunSnapshot(string $runId): array
    {
        $db = $this->connection();
        $run = $db->table('media_backfill_runs')->useWritePdo()->where('run_id', $runId)->first();
        if (! $run instanceof stdClass) {
            throw new ReconciliationException(ReconciliationError::InvalidInput);
        }
        $items = $db->table('media_backfill_items')->useWritePdo()->where('run_id', $runId)
            ->orderBy('id')->limit(self::MAX_ITEMS + 1)->get()->all();
        if (count($items) > self::MAX_ITEMS
            || $db->table('media_backfill_items')->useWritePdo()->where('run_id', $runId)->count() !== count($items)) {
            throw new ReconciliationException(ReconciliationError::InconsistentJournal);
        }
        $objects = $db->table('media_backfill_objects as objects')->useWritePdo()
            ->join('media_backfill_items as items', 'items.id', '=', 'objects.item_id')
            ->where('items.run_id', $runId)->select('objects.*')->orderBy('objects.id')->get()->all();

        return [$run, $items, $objects];
    }

    /** @return array{stdClass, stdClass, list<stdClass>} */
    private function loadItemSnapshot(string $runId, int $itemId): array
    {
        $db = $this->connection();
        $run = $this->loadRun($runId);
        $item = $db->table('media_backfill_items')->useWritePdo()->where('id', $itemId)->first();
        if (! $item instanceof stdClass || (string) ($item->run_id ?? '') !== $runId) {
            throw new ReconciliationException(ReconciliationError::InconsistentJournal);
        }
        $objects = $db->table('media_backfill_objects')->useWritePdo()->where('item_id', $itemId)
            ->orderBy('id')->limit(self::MAX_ITEMS + 1)->get()->all();
        if (count($objects) > self::MAX_ITEMS
            || $db->table('media_backfill_objects')->useWritePdo()->where('item_id', $itemId)->count() !== count($objects)) {
            throw new ReconciliationException(ReconciliationError::InconsistentJournal);
        }

        return [$run, $item, $objects];
    }

    private function loadRun(string $runId): stdClass
    {
        $run = $this->connection()->table('media_backfill_runs')->useWritePdo()
            ->where('run_id', $runId)->first();
        if (! $run instanceof stdClass) {
            throw new ReconciliationException(ReconciliationError::InvalidInput);
        }

        return $run;
    }

    private function connection(): Connection
    {
        $this->assertConnection();

        return $this->database->connection();
    }

    private function assertConnection(): void
    {
        $db = $this->database->connection();
        if ($db->getDriverName() !== 'mariadb') {
            throw new ReconciliationException(ReconciliationError::JournalUnavailable);
        }
        if ($db->transactionLevel() !== 0 || $db->getPdo()->inTransaction()) {
            throw new ReconciliationException(ReconciliationError::AmbientTransaction);
        }
    }

    protected function currentIdentity(): StorageIdentity
    {
        return StorageIdentity::current($this->database);
    }

    protected function moment(): DateTimeInterface
    {
        return new DateTimeImmutable('now', new DateTimeZone('UTC'));
    }

    protected function uuid(): string
    {
        return (string) Str::uuid();
    }

    private function runState(stdClass $run): RunState
    {
        $state = is_string($run->state ?? null) ? RunState::tryFrom($run->state) : null;
        if ($state === null) {
            throw new ReconciliationException(ReconciliationError::InconsistentJournal);
        }

        return $state;
    }

    private function safeRunState(string $runId): ?RunState
    {
        try {
            return $this->runState($this->loadRun($runId));
        } catch (Throwable) {
            return null;
        }
    }

    private function blockReason(Throwable $error): ?ReconciliationBlockReason
    {
        if ($error instanceof OperationalEvidenceException) {
            return $error->reason;
        }
        if (! $error instanceof ReconciliationException) {
            return null;
        }

        return match ($error->reason) {
            ReconciliationError::InconsistentJournal => ReconciliationBlockReason::InconsistentJournal,
            ReconciliationError::EvidenceMismatch => ReconciliationBlockReason::EvidenceMismatch,
            ReconciliationError::ReplayConflict,
            ReconciliationError::IllegalResolution => ReconciliationBlockReason::ResolutionConflict,
            ReconciliationError::IdentityMismatch,
            ReconciliationError::UnsupportedStorage => ReconciliationBlockReason::StorageObservationUntrusted,
            default => null,
        };
    }

    private function release(AdvisoryLockHandle $lock, ReconciliationReport $report): ReconciliationReport
    {
        try {
            $lock->release();

            return $report;
        } catch (Throwable) {
            return $report->withOutcome(ReconciliationOutcome::SafetyFailure, null);
        }
    }

    private function emptyReport(
        ReconciliationInvocation $invocation,
        ReconciliationOutcome $outcome,
    ): ReconciliationReport {
        return $this->report($outcome, $invocation, null, false, 0, 0, 0, 0, null, false, null, null, false);
    }

    private function report(
        ReconciliationOutcome $outcome,
        ReconciliationInvocation $invocation,
        ?string $attemptId,
        bool $noOp,
        int $traversed,
        int $skipped,
        int $resolvedForward,
        int $resolvedNoEffect,
        ?ReconciliationBlockReason $blocker,
        bool $closureAppended,
        ?RunState $runState,
        ?RecoveryBarrierState $barrier,
        ?bool $durableProgress,
    ): ReconciliationReport {
        return new ReconciliationReport(
            $outcome,
            $invocation->runId,
            $attemptId,
            $noOp,
            $traversed,
            $skipped,
            $resolvedForward,
            $resolvedNoEffect,
            $blocker,
            $closureAppended,
            $runState,
            $barrier,
            $durableProgress,
        );
    }
}
