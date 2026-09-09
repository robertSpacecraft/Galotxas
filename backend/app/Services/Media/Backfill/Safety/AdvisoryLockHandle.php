<?php

namespace App\Services\Media\Backfill\Safety;

use Illuminate\Database\Connection;
use PDO;
use Throwable;

final class AdvisoryLockHandle
{
    private bool $lost = false;

    private bool $released = false;

    public readonly string $name;

    public function __construct(
        private ?Connection $connection,
        private ?PDO $pdo,
        public readonly StorageIdentity $identity,
        public readonly string $connectionId,
    ) {
        $this->name = $identity->lockName();
    }

    public function assertOwned(): void
    {
        try {
            if ($this->lost || $this->pdo === null) {
                throw new BackfillSafetyException(SafetyError::LockLost);
            }
            // Never call Connection::select/getPdo here: Laravel must not reconnect this handle.
            $current = (string) $this->pdo->query('SELECT CONNECTION_ID()')->fetchColumn();
            $query = $this->pdo->prepare('SELECT IS_USED_LOCK(?)');
            $query->execute([$this->name]);
            if ($current !== $this->connectionId || (string) $query->fetchColumn() !== $this->connectionId) {
                throw new BackfillSafetyException(SafetyError::LockLost);
            }
        } catch (Throwable) {
            $this->lost = true;
            throw new BackfillSafetyException(SafetyError::LockLost);
        }
    }

    public function release(): void
    {
        if ($this->released) {
            return;
        }
        try {
            $this->assertOwned();
            try {
                $query = $this->pdo->prepare('SELECT RELEASE_LOCK(?)');
                $query->execute([$this->name]);
                if ((string) $query->fetchColumn() !== '1') {
                    throw new BackfillSafetyException(SafetyError::LockReleaseFailed);
                }
                $this->released = true;
            } catch (Throwable) {
                throw new BackfillSafetyException(SafetyError::LockReleaseFailed);
            }
        } finally {
            $this->close();
        }
    }

    /** Drop both references to the nonpersistent session, including after a failed release. */
    public function close(): void
    {
        $this->lost = true;
        $this->connection?->disconnect();
        $this->pdo = null;
        $this->connection = null;
    }

    public function __destruct()
    {
        $this->close();
    }

    private function __clone() {}
}
