<?php

namespace App\Services\Media;

use Illuminate\Contracts\Database\ConcurrencyErrorDetector;
use Illuminate\Database\Connection;
use Illuminate\Support\Facades\DB;
use Throwable;

/** Coordinates only managed image mutations; it owns no process-wide listeners/state. */
class ResponsiveMediaLifecycle
{
    public function __construct(
        private readonly ResponsiveMediaStorage $storage,
        private readonly ConcurrencyErrorDetector $concurrencyErrors,
    ) {}

    /**
     * Storage has already succeeded, outside the retry loop. The mutation remembers
     * obsolete references captured under domain locks using the supplied callback.
     *
     * @template T
     *
     * @param  callable(callable(mixed, ResponsiveImageProfile): void): T  $mutation
     * @return T
     */
    public function mutate(?StoredResponsiveSet $newSet, callable $mutation, int $attempts = 1): mixed
    {
        $connection = DB::connection();
        $ambientLevel = $connection->transactionLevel();
        $obsolete = [];
        $committed = false;
        $compensated = false;
        $compensate = function () use ($newSet, &$compensated, &$committed): void {
            if ($newSet !== null && ! $compensated && ! $committed) {
                $compensated = true;
                $this->storage->deleteSet($newSet->masterKey, $newSet->manifest->profile);
            }
        };

        // Laravel's commit retry does not release the failed manager record. Own
        // root retries so PDO and the manager are recovered BEFORE another begin.
        // A caller-owned transaction must propagate concurrency failures outward.
        $maxAttempts = $ambientLevel === 0 ? max(1, $attempts) : 1;
        for ($attempt = 1; $attempt <= $maxAttempts; $attempt++) {
            try {
                $result = $connection->transaction(function () use ($mutation, &$obsolete, &$committed) {
                    // Failed attempts cannot publish obsolete-media cleanup.
                    $obsolete = [];
                    DB::afterCommit(static function () use (&$committed): void {
                        $committed = true;
                    });

                    return $mutation(static function (mixed $key, ResponsiveImageProfile $profile) use (&$obsolete): void {
                        if (is_string($key)) {
                            $obsolete[$key] = $profile;
                        }
                    });
                }, 1);

                break;
            } catch (Throwable $exception) {
                if ($ambientLevel === 0 && ! $committed) {
                    $this->rollBackFailedRoot($connection);
                    if ($attempt < $maxAttempts && $this->concurrencyErrors->causedByConcurrencyError($exception)) {
                        continue;
                    }
                }
                $compensate();

                throw $exception;
            }
        }

        if ($newSet !== null && ! $committed) {
            // Laravel 12.63 drops rollback callbacks on already committed children.
            // Attach to EVERY still-pending ancestor after the internal retry boundary.
            // Any containing savepoint/root rollback compensates once. Root completion
            // releases all records/captures. No dynamic global event listener is needed.
            foreach (app('db.transactions')->getPendingTransactions() as $transaction) {
                if ($transaction->connection === DB::connection()->getName()) {
                    $transaction->addCallbackForRollback($compensate);
                }
            }
        }

        foreach ($obsolete as $key => $profile) {
            if ($key !== $newSet?->masterKey) {
                $this->cleanupAfterCommit($key, $profile);
            }
        }

        return $result;
    }

    public function cleanupAfterCommit(mixed $key, ResponsiveImageProfile $profile): void
    {
        DB::afterCommit(fn () => $this->storage->deleteSet($key, $profile));
    }

    private function rollBackFailedRoot(Connection $connection): void
    {
        // Laravel's final handleCommitTransactionException decrements the level
        // without rolling PDO back or releasing transaction records. Only recover
        // a root owned by this invocation; never roll back the caller's transaction.
        try {
            if ($connection->transactionLevel() > 0) {
                $connection->rollBack(0);
            } elseif ($connection->getPdo()->inTransaction()) {
                $connection->getPdo()->rollBack();
            }
        } catch (Throwable) {
            // A failed rollback must not leave this session able to commit later.
            $connection->disconnect();
        }

        $manager = app('db.transactions');
        if ($manager->getPendingTransactions()->contains('connection', $connection->getName())
            || $manager->getCommittedTransactions()->contains('connection', $connection->getName())) {
            $manager->rollback($connection->getName(), 0);
        }
    }
}
