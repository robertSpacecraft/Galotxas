<?php

namespace App\Services\Media\Backfill\Safety;

use Illuminate\Database\Connectors\ConnectionFactory;
use Illuminate\Database\DatabaseManager;
use PDO;
use Throwable;

class MariaDbBackfillLock
{
    public function __construct(private readonly DatabaseManager $database, private readonly ConnectionFactory $factory) {}

    public function acquire(StorageIdentity $identity): LockAcquisition
    {
        $connection = null;
        try {
            if (! hash_equals(StorageIdentity::current($this->database)->hash, $identity->hash)) {
                return new LockAcquisition(LockAcquireState::Failed);
            }
            $config = $this->database->connection()->getConfig();
            // Factory-owned, never registered in DatabaseManager; persistent sessions are forbidden.
            unset($config['url']);
            $config['options'][PDO::ATTR_PERSISTENT] = false;
            $config['options'][PDO::ATTR_ERRMODE] = PDO::ERRMODE_EXCEPTION;
            $connection = $this->factory->make($config, 'media_backfill_lock');
            $pdo = $connection->getPdo();
            $id = (string) $pdo->query('SELECT CONNECTION_ID()')->fetchColumn();
            if (! ctype_digit($id) || $id === '0') {
                throw new BackfillSafetyException(SafetyError::LockLost);
            }
            $query = $pdo->prepare('SELECT GET_LOCK(?, 0)');
            $query->execute([$identity->lockName()]);
            $result = $query->fetchColumn();
            if ((string) $result === '1') {
                return new LockAcquisition(LockAcquireState::Acquired,
                    new AdvisoryLockHandle($connection, $pdo, $identity, $id));
            }
            $connection->disconnect();

            return new LockAcquisition((string) $result === '0' ? LockAcquireState::Busy : LockAcquireState::Failed);
        } catch (Throwable) {
            $connection?->disconnect();

            return new LockAcquisition(LockAcquireState::Failed);
        }
    }
}
