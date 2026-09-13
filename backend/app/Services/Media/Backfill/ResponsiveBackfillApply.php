<?php

namespace App\Services\Media\Backfill;

use App\Services\Media\Backfill\Safety\AdvisoryLockHandle;
use App\Services\Media\Backfill\Safety\ApplyJournal;
use App\Services\Media\Backfill\Safety\ApplyMaintenanceGuard;
use App\Services\Media\Backfill\Safety\ApplyResult;
use App\Services\Media\Backfill\Safety\ApplyRunSelection;
use App\Services\Media\Backfill\Safety\BackfillSafetyException;
use App\Services\Media\Backfill\Safety\ItemPhase;
use App\Services\Media\Backfill\Safety\LockAcquireState;
use App\Services\Media\Backfill\Safety\MariaDbBackfillLock;
use App\Services\Media\Backfill\Safety\RecoveryBarrierState;
use App\Services\Media\Backfill\Safety\RunState;
use App\Services\Media\Backfill\Safety\SafetyError;
use App\Services\Media\Backfill\Safety\StorageIdentity;
use Illuminate\Database\DatabaseManager;
use Throwable;

/** Internal one-domain APPLY coordinator. It is deliberately not a CLI entrypoint. */
class ResponsiveBackfillApply
{
    public function __construct(
        private readonly ApplyMaintenanceGuard $maintenance,
        private readonly DatabaseManager $database,
        private readonly ApplyJournal $journal,
        private readonly MariaDbBackfillLock $locks,
        private readonly ManagedMediaReferenceRegistry $registry,
        private readonly ResponsiveBackfillPreflight $preflight,
        private readonly ApplyItemPublisher $publisher,
    ) {}

    public function run(ApplyInvocation $invocation): ApplyReport
    {
        $classificationCounts = $this->emptyClassificationCounts();
        $resultCounts = $this->emptyResultCounts();

        try {
            $this->maintenance->assertAllowed();
        } catch (BackfillSafetyException $error) {
            return $this->preRunFailure($invocation, $classificationCounts, $resultCounts,
                $error->reason === SafetyError::MaintenanceRequired
                    ? ApplyOutcome::MaintenanceRequired
                    : ApplyOutcome::SafeFailure);
        } catch (Throwable) {
            return $this->preRunFailure($invocation, $classificationCounts, $resultCounts, ApplyOutcome::SafeFailure);
        }

        try {
            $identity = $this->currentIdentity();
        } catch (Throwable) {
            return $this->preRunFailure($invocation, $classificationCounts, $resultCounts, ApplyOutcome::SafeFailure);
        }

        try {
            if ($this->journal->recoveryBarrier() !== RecoveryBarrierState::Clear) {
                return $this->preRunFailure($invocation, $classificationCounts, $resultCounts, ApplyOutcome::ReconciliationRequired);
            }
        } catch (Throwable) {
            return $this->preRunFailure($invocation, $classificationCounts, $resultCounts, ApplyOutcome::ReconciliationRequired);
        }

        try {
            $acquisition = $this->locks->acquire($identity);
        } catch (Throwable) {
            return $this->preRunFailure($invocation, $classificationCounts, $resultCounts, ApplyOutcome::LockAcquireFailed);
        }

        $lock = $acquisition->handle;
        if ($acquisition->state === LockAcquireState::Busy && $lock === null) {
            return $this->preRunFailure($invocation, $classificationCounts, $resultCounts, ApplyOutcome::LockBusy);
        }
        if ($acquisition->state !== LockAcquireState::Acquired || $lock === null) {
            $report = $this->preRunFailure($invocation, $classificationCounts, $resultCounts, ApplyOutcome::LockAcquireFailed);

            return $lock === null ? $report : $this->release($lock, $report);
        }

        try {
            $report = $this->runLocked($invocation, $identity, $lock, $classificationCounts, $resultCounts);
        } catch (Throwable) {
            $report = $this->preRunFailure($invocation, $classificationCounts, $resultCounts, ApplyOutcome::ReconciliationRequired);
        }

        return $this->release($lock, $report);
    }

    /**
     * @param  array<string, int>  $classificationCounts
     * @param  array<string, int>  $resultCounts
     */
    private function runLocked(
        ApplyInvocation $invocation,
        StorageIdentity $identity,
        AdvisoryLockHandle $lock,
        array $classificationCounts,
        array $resultCounts,
    ): ApplyReport {
        $upperBound = null;
        $runId = null;
        $checkpoint = $invocation->afterId;
        $observed = 0;
        $publicationBegan = false;
        $items = [];

        try {
            if (($gateFailure = $this->preRunGatesFailure($identity, $lock)) !== null) {
                return $this->preRunFailure(
                    $invocation,
                    $classificationCounts,
                    $resultCounts,
                    $gateFailure,
                );
            }
            $upperBound = $this->registry->upperBound($invocation->domain);
            if (($gateFailure = $this->preRunGatesFailure($identity, $lock)) !== null) {
                return $this->preRunFailure(
                    $invocation,
                    $classificationCounts,
                    $resultCounts,
                    $gateFailure,
                    $upperBound,
                );
            }

            $runId = $this->journal->createApplyRun(
                $identity,
                new ApplyRunSelection($invocation->domain, $invocation->afterId, $invocation->limit, $upperBound),
                null,
            );
            $this->journal->heartbeat($runId);

            $cursor = $invocation->afterId;
            if ($upperBound > $cursor) {
                while ($observed < $invocation->limit) {
                    $batch = $this->registry->batch($invocation->domain, $cursor, 1, $upperBound);
                    if ($batch === []) {
                        break;
                    }

                    $reference = $batch[0];
                    $cursor = $reference->id;
                    $observed++;
                    $result = $this->preflight->inspect($reference);
                    $itemId = $this->journal->snapshot($runId, $result);
                    $classification = $result->classification;
                    $classificationCounts[$classification->value]++;
                    $items[] = [
                        'item_id' => $itemId,
                        'entity_id' => $reference->id,
                        'classification' => $classification,
                        'result' => null,
                    ];
                    $this->journal->heartbeat($runId);
                    unset($result, $reference, $batch);
                }
            }

            $hasContinuation = $observed === $invocation->limit
                && $this->registry->hasReferences($invocation->domain, $cursor, $upperBound);

            if ($this->hasBlocker($items)) {
                $this->terminalizeUntouched($runId, $items, 0, $resultCounts);

                return $this->finishKnownRun(
                    $invocation,
                    $identity,
                    $lock,
                    $runId,
                    $upperBound,
                    $observed,
                    $classificationCounts,
                    $resultCounts,
                    $checkpoint,
                    RunState::Failed,
                    ApplyOutcome::FirstPassBlocked,
                );
            }

            foreach ($items as $index => &$item) {
                $classification = $item['classification'];
                if (! DryRunClassificationPolicy::isCandidate($classification)) {
                    $this->finishItem($runId, $item, ApplyResult::Skipped, $resultCounts);
                    $this->journal->advanceCheckpoint($runId, $invocation->domain, $item['entity_id']);
                    $checkpoint = $item['entity_id'];
                    $this->journal->heartbeat($runId);

                    continue;
                }

                $publicationBegan = true;
                try {
                    $applyResult = $this->publisher->publish($runId, $item['item_id'], $lock);
                    $item['result'] = $applyResult;
                    $resultCounts[$applyResult->value]++;
                    $this->journal->heartbeat($runId);
                } catch (BackfillSafetyException $error) {
                    return $this->publisherSafetyFailure(
                        $invocation,
                        $identity,
                        $lock,
                        $runId,
                        $upperBound,
                        $observed,
                        $classificationCounts,
                        $resultCounts,
                        $checkpoint,
                        $items,
                        $index,
                        $error,
                    );
                } catch (Throwable) {
                    return $this->ambiguousPublisherFailure(
                        $invocation,
                        $identity,
                        $lock,
                        $runId,
                        $upperBound,
                        $observed,
                        $classificationCounts,
                        $resultCounts,
                        $checkpoint,
                        $items,
                        $index,
                    );
                }

                if (in_array($applyResult, [ApplyResult::Published, ApplyResult::Skipped], true)) {
                    $this->journal->advanceCheckpoint($runId, $invocation->domain, $item['entity_id']);
                    $checkpoint = $item['entity_id'];
                    $this->journal->heartbeat($runId);

                    continue;
                }

                if (in_array($applyResult, [
                    ApplyResult::ReferenceChanged,
                    ApplyResult::CollisionDetected,
                    ApplyResult::FailedNoWrites,
                ], true)) {
                    $this->journal->advanceCheckpoint($runId, $invocation->domain, $item['entity_id']);
                    $checkpoint = $item['entity_id'];
                    $this->journal->heartbeat($runId);
                    $this->terminalizeUntouched($runId, $items, $index + 1, $resultCounts);

                    return $this->finishKnownRun(
                        $invocation,
                        $identity,
                        $lock,
                        $runId,
                        $upperBound,
                        $observed,
                        $classificationCounts,
                        $resultCounts,
                        $checkpoint,
                        RunState::Failed,
                        ApplyOutcome::SafeFailure,
                    );
                }

                $this->terminalizeUntouched($runId, $items, $index + 1, $resultCounts);

                return $this->finishKnownRun(
                    $invocation,
                    $identity,
                    $lock,
                    $runId,
                    $upperBound,
                    $observed,
                    $classificationCounts,
                    $resultCounts,
                    $checkpoint,
                    $applyResult === ApplyResult::FailedCleanupIncomplete ? RunState::Failed : RunState::Interrupted,
                    ApplyOutcome::ReconciliationRequired,
                    $applyResult === ApplyResult::PublicationUnknown ? SafetyError::PublicationUnknown : null,
                );
            }
            unset($item);

            return $this->finishKnownRun(
                $invocation,
                $identity,
                $lock,
                $runId,
                $upperBound,
                $observed,
                $classificationCounts,
                $resultCounts,
                $checkpoint,
                RunState::Completed,
                ApplyOutcome::Success,
                continuationAfterId: $hasContinuation ? $checkpoint : null,
            );
        } catch (Throwable $error) {
            if ($runId === null) {
                return $this->preRunFailure(
                    $invocation,
                    $classificationCounts,
                    $resultCounts,
                    $this->preRunOutcome($error),
                    $upperBound,
                );
            }
            if ($publicationBegan || $this->isJournalAmbiguity($error)) {
                return $this->untrustedRunReport(
                    $invocation,
                    $upperBound,
                    $observed,
                    $classificationCounts,
                    $resultCounts,
                    $checkpoint,
                );
            }

            try {
                $this->terminalizeUntouched($runId, $items, 0, $resultCounts);

                return $this->finishKnownRun(
                    $invocation,
                    $identity,
                    $lock,
                    $runId,
                    $upperBound,
                    $observed,
                    $classificationCounts,
                    $resultCounts,
                    $checkpoint,
                    RunState::Failed,
                    $error instanceof BackfillSafetyException && $error->reason === SafetyError::MaintenanceRequired
                        ? ApplyOutcome::MaintenanceRequired
                        : ApplyOutcome::SafeFailure,
                    $error instanceof BackfillSafetyException ? $error->reason : null,
                );
            } catch (Throwable) {
                return $this->untrustedRunReport(
                    $invocation,
                    $upperBound,
                    $observed,
                    $classificationCounts,
                    $resultCounts,
                    $checkpoint,
                );
            }
        }
    }

    private function preRunGatesFailure(StorageIdentity $identity, AdvisoryLockHandle $lock): ?ApplyOutcome
    {
        try {
            $lock->assertOwned();
        } catch (Throwable) {
            return ApplyOutcome::SafeFailure;
        }

        try {
            $this->maintenance->assertAllowed();
        } catch (BackfillSafetyException $error) {
            return $error->reason === SafetyError::MaintenanceRequired
                ? ApplyOutcome::MaintenanceRequired
                : ApplyOutcome::SafeFailure;
        } catch (Throwable) {
            return ApplyOutcome::SafeFailure;
        }

        try {
            $current = $this->currentIdentity();
            if (! hash_equals($identity->hash, $lock->identity->hash)) {
                return ApplyOutcome::LockAcquireFailed;
            }
            if (! hash_equals($identity->hash, $current->hash)) {
                return ApplyOutcome::SafeFailure;
            }
        } catch (Throwable) {
            return ApplyOutcome::SafeFailure;
        }

        try {
            if ($this->journal->recoveryBarrier() !== RecoveryBarrierState::Clear) {
                return ApplyOutcome::ReconciliationRequired;
            }
        } catch (Throwable) {
            return ApplyOutcome::ReconciliationRequired;
        }

        return null;
    }

    protected function currentIdentity(): StorageIdentity
    {
        return StorageIdentity::current($this->database);
    }

    /** @param list<array{classification: PreflightClassification}> $items */
    private function hasBlocker(array $items): bool
    {
        foreach ($items as $item) {
            if (DryRunClassificationPolicy::isBlocking($item['classification'])) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array{item_id: int, entity_id: int, classification: PreflightClassification, result: ?ApplyResult}  $item
     * @param  array<string, int>  $resultCounts
     */
    private function finishItem(string $runId, array &$item, ApplyResult $result, array &$resultCounts): void
    {
        $this->journal->finishItem($item['item_id'], $result);
        $item['result'] = $result;
        $resultCounts[$result->value]++;
        $this->journal->heartbeat($runId);
    }

    /**
     * @param  list<array{item_id: int, entity_id: int, classification: PreflightClassification, result: ?ApplyResult}>  $items
     * @param  array<string, int>  $resultCounts
     */
    private function terminalizeUntouched(string $runId, array &$items, int $from, array &$resultCounts): void
    {
        foreach ($items as $index => &$item) {
            if ($index < $from || $item['result'] !== null) {
                continue;
            }
            $result = DryRunClassificationPolicy::isCandidate($item['classification'])
                || DryRunClassificationPolicy::isBlocking($item['classification'])
                ? ApplyResult::FailedNoWrites
                : ApplyResult::Skipped;
            $this->finishItem($runId, $item, $result, $resultCounts);
        }
        unset($item);
    }

    /**
     * @param  array<string, int>  $classificationCounts
     * @param  array<string, int>  $resultCounts
     * @param  list<array{item_id: int, entity_id: int, classification: PreflightClassification, result: ?ApplyResult}>  $items
     */
    private function publisherSafetyFailure(
        ApplyInvocation $invocation,
        StorageIdentity $identity,
        AdvisoryLockHandle $lock,
        string $runId,
        int $upperBound,
        int $observed,
        array $classificationCounts,
        array $resultCounts,
        int $checkpoint,
        array &$items,
        int $index,
        BackfillSafetyException $error,
    ): ApplyReport {
        if ($this->isJournalAmbiguity($error)) {
            return $this->ambiguousPublisherFailure(
                $invocation,
                $identity,
                $lock,
                $runId,
                $upperBound,
                $observed,
                $classificationCounts,
                $resultCounts,
                $checkpoint,
                $items,
                $index,
            );
        }

        try {
            $row = $this->journal->item($items[$index]['item_id']);
            $durable = $row->phase === ItemPhase::Finished->value && is_string($row->apply_result)
                ? ApplyResult::tryFrom($row->apply_result)
                : null;
            if ($durable === null) {
                return $this->ambiguousPublisherFailure(
                    $invocation,
                    $identity,
                    $lock,
                    $runId,
                    $upperBound,
                    $observed,
                    $classificationCounts,
                    $resultCounts,
                    $checkpoint,
                    $items,
                    $index,
                );
            }

            $items[$index]['result'] = $durable;
            $resultCounts[$durable->value]++;
            if (in_array($durable, [
                ApplyResult::Published,
                ApplyResult::Skipped,
                ApplyResult::ReferenceChanged,
                ApplyResult::CollisionDetected,
                ApplyResult::FailedNoWrites,
            ], true)) {
                $this->journal->advanceCheckpoint($runId, $invocation->domain, $items[$index]['entity_id']);
                $checkpoint = $items[$index]['entity_id'];
                $this->journal->heartbeat($runId);
                $this->terminalizeUntouched($runId, $items, $index + 1, $resultCounts);

                return $this->finishKnownRun(
                    $invocation,
                    $identity,
                    $lock,
                    $runId,
                    $upperBound,
                    $observed,
                    $classificationCounts,
                    $resultCounts,
                    $checkpoint,
                    RunState::Failed,
                    $error->reason === SafetyError::MaintenanceRequired
                        ? ApplyOutcome::MaintenanceRequired
                        : ApplyOutcome::SafeFailure,
                    $error->reason,
                );
            }

            $this->terminalizeUntouched($runId, $items, $index + 1, $resultCounts);

            return $this->finishKnownRun(
                $invocation,
                $identity,
                $lock,
                $runId,
                $upperBound,
                $observed,
                $classificationCounts,
                $resultCounts,
                $checkpoint,
                $durable === ApplyResult::FailedCleanupIncomplete ? RunState::Failed : RunState::Interrupted,
                ApplyOutcome::ReconciliationRequired,
                $durable === ApplyResult::PublicationUnknown ? SafetyError::PublicationUnknown : $error->reason,
            );
        } catch (Throwable) {
            return $this->untrustedRunReport(
                $invocation,
                $upperBound,
                $observed,
                $classificationCounts,
                $resultCounts,
                $checkpoint,
            );
        }
    }

    /**
     * @param  array<string, int>  $classificationCounts
     * @param  array<string, int>  $resultCounts
     * @param  list<array{item_id: int, entity_id: int, classification: PreflightClassification, result: ?ApplyResult}>  $items
     */
    private function ambiguousPublisherFailure(
        ApplyInvocation $invocation,
        StorageIdentity $identity,
        AdvisoryLockHandle $lock,
        string $runId,
        int $upperBound,
        int $observed,
        array $classificationCounts,
        array $resultCounts,
        int $checkpoint,
        array &$items,
        int $index,
    ): ApplyReport {
        try {
            $row = $this->journal->item($items[$index]['item_id']);
            if ($row->phase === ItemPhase::Finished->value && is_string($row->apply_result)
                && ($durable = ApplyResult::tryFrom($row->apply_result)) !== null) {
                $items[$index]['result'] = $durable;
                $resultCounts[$durable->value]++;
            }
            $this->terminalizeUntouched($runId, $items, $index + 1, $resultCounts);

            return $this->finishKnownRun(
                $invocation,
                $identity,
                $lock,
                $runId,
                $upperBound,
                $observed,
                $classificationCounts,
                $resultCounts,
                $checkpoint,
                RunState::Interrupted,
                ApplyOutcome::ReconciliationRequired,
            );
        } catch (Throwable) {
            return $this->untrustedRunReport(
                $invocation,
                $upperBound,
                $observed,
                $classificationCounts,
                $resultCounts,
                $checkpoint,
            );
        }
    }

    /**
     * @param  array<string, int>  $classificationCounts
     * @param  array<string, int>  $resultCounts
     */
    private function finishKnownRun(
        ApplyInvocation $invocation,
        StorageIdentity $identity,
        AdvisoryLockHandle $lock,
        string $runId,
        int $upperBound,
        int $observed,
        array $classificationCounts,
        array $resultCounts,
        int $checkpoint,
        RunState $state,
        ApplyOutcome $outcome,
        ?SafetyError $error = null,
        ?int $continuationAfterId = null,
    ): ApplyReport {
        try {
            $this->assertFinalLockOwned($lock);
        } catch (Throwable) {
            return $this->untrustedRunReport(
                $invocation,
                $upperBound,
                $observed,
                $classificationCounts,
                $resultCounts,
                $checkpoint,
            );
        }

        try {
            $this->assertFinalMaintenanceAndIdentity($identity, $lock);
        } catch (BackfillSafetyException $guardError) {
            if ($outcome !== ApplyOutcome::ReconciliationRequired) {
                $outcome = $guardError->reason === SafetyError::MaintenanceRequired
                    ? ApplyOutcome::MaintenanceRequired
                    : ApplyOutcome::SafeFailure;
                $state = RunState::Failed;
                $continuationAfterId = null;
                $error = $guardError->reason;
            }
        } catch (Throwable) {
            $outcome = ApplyOutcome::ReconciliationRequired;
            $state = RunState::Interrupted;
            $continuationAfterId = null;
        }

        try {
            $this->journal->finishRun($runId, $state, $this->summary($classificationCounts, $resultCounts), $error);
        } catch (Throwable) {
            return $this->untrustedRunReport(
                $invocation,
                $upperBound,
                $observed,
                $classificationCounts,
                $resultCounts,
                $checkpoint,
            );
        }

        if ($outcome === ApplyOutcome::ReconciliationRequired) {
            try {
                $this->journal->recoveryBarrier();
            } catch (Throwable) {
                // The outcome already requires reconciliation; never downgrade it.
            }
        } else {
            try {
                if ($this->journal->recoveryBarrier() !== RecoveryBarrierState::Clear) {
                    $outcome = ApplyOutcome::ReconciliationRequired;
                    $continuationAfterId = null;
                }
            } catch (Throwable) {
                $outcome = ApplyOutcome::ReconciliationRequired;
                $continuationAfterId = null;
            }
        }

        return new ApplyReport(
            $outcome,
            $invocation->domain,
            $invocation->afterId,
            $upperBound,
            $invocation->limit,
            $observed,
            $classificationCounts,
            $resultCounts,
            $checkpoint,
            $state,
            $continuationAfterId,
        );
    }

    protected function assertFinalLockOwned(AdvisoryLockHandle $lock): void
    {
        $lock->assertOwned();
    }

    private function assertFinalMaintenanceAndIdentity(StorageIdentity $identity, AdvisoryLockHandle $lock): void
    {
        $this->maintenance->assertAllowed();
        $current = $this->currentIdentity();
        if (! hash_equals($identity->hash, $current->hash)
            || ! hash_equals($identity->hash, $lock->identity->hash)) {
            throw new BackfillSafetyException(SafetyError::IdentityMismatch);
        }
    }

    private function release(AdvisoryLockHandle $lock, ApplyReport $report): ApplyReport
    {
        try {
            $this->releaseLock($lock);
        } catch (Throwable) {
            if ($report->outcome !== ApplyOutcome::ReconciliationRequired) {
                return $report->withOutcome(ApplyOutcome::SafeFailure);
            }
        }

        return $report;
    }

    protected function releaseLock(AdvisoryLockHandle $lock): void
    {
        $lock->release();
    }

    private function preRunOutcome(Throwable $error): ApplyOutcome
    {
        if ($error instanceof BackfillSafetyException) {
            return match ($error->reason) {
                SafetyError::MaintenanceRequired => ApplyOutcome::MaintenanceRequired,
                SafetyError::JournalUnavailable, SafetyError::PublicationUnknown,
                SafetyError::ReconciliationRequired => ApplyOutcome::ReconciliationRequired,
                default => ApplyOutcome::SafeFailure,
            };
        }

        return ApplyOutcome::SafeFailure;
    }

    private function isJournalAmbiguity(Throwable $error): bool
    {
        return $error instanceof BackfillSafetyException && in_array($error->reason, [
            SafetyError::JournalUnavailable,
            SafetyError::AmbientTransaction,
            SafetyError::IllegalTransition,
            SafetyError::PublicationUnknown,
            SafetyError::ReconciliationRequired,
        ], true);
    }

    /**
     * @param  array<string, int>  $classificationCounts
     * @param  array<string, int>  $resultCounts
     */
    private function preRunFailure(
        ApplyInvocation $invocation,
        array $classificationCounts,
        array $resultCounts,
        ApplyOutcome $outcome,
        ?int $upperBound = null,
    ): ApplyReport {
        return new ApplyReport(
            $outcome,
            $invocation->domain,
            $invocation->afterId,
            $upperBound,
            $invocation->limit,
            0,
            $classificationCounts,
            $resultCounts,
            null,
            null,
        );
    }

    /**
     * @param  array<string, int>  $classificationCounts
     * @param  array<string, int>  $resultCounts
     */
    private function untrustedRunReport(
        ApplyInvocation $invocation,
        int $upperBound,
        int $observed,
        array $classificationCounts,
        array $resultCounts,
        int $checkpoint,
    ): ApplyReport {
        return new ApplyReport(
            ApplyOutcome::ReconciliationRequired,
            $invocation->domain,
            $invocation->afterId,
            $upperBound,
            $invocation->limit,
            $observed,
            $classificationCounts,
            $resultCounts,
            $checkpoint,
            null,
        );
    }

    /** @return array<string, int> */
    private function emptyClassificationCounts(): array
    {
        return array_fill_keys(array_map(
            static fn (PreflightClassification $classification): string => $classification->value,
            PreflightClassification::cases(),
        ), 0);
    }

    /** @return array<string, int> */
    private function emptyResultCounts(): array
    {
        return array_fill_keys(array_map(
            static fn (ApplyResult $result): string => $result->value,
            ApplyResult::cases(),
        ), 0);
    }

    /**
     * @param  array<string, int>  $classificationCounts
     * @param  array<string, int>  $resultCounts
     * @return array<string, int>
     */
    private function summary(array $classificationCounts, array $resultCounts): array
    {
        return $classificationCounts + $resultCounts;
    }
}
