<?php

namespace App\Services\Media\Backfill\Safety;

use Illuminate\Database\DatabaseManager;
use Throwable;

/** One object only. D1C must provide revalidation and variant-before-manifest sequencing. */
class JournaledObjectWriter
{
    public function __construct(
        private readonly ApplyJournal $journal,
        private readonly ApplyMaintenanceGuard $maintenance,
        private readonly ExclusiveObjectCreator $creator,
        private readonly DatabaseManager $database,
    ) {}

    public function create(int $objectId, string $bytes, AdvisoryLockHandle $lock): CreateReceipt
    {
        $target = $this->journal->target($objectId);
        $target->validateBytes($bytes);
        $object = $this->journal->object($objectId);
        $item = $this->journal->item($object->item_id);
        $run = $this->journal->run($item->run_id);
        $identity = StorageIdentity::current($this->database);
        if (! hash_equals($identity->hash, $run->storage_identity_hash) || ! hash_equals($identity->hash, $lock->identity->hash)) {
            throw new BackfillSafetyException(SafetyError::IdentityMismatch);
        }
        $this->maintenance->assertAllowed();
        $lock->assertOwned();
        $this->journal->commitIntent($objectId);
        // A failed intent never reaches storage. Recheck after commit, immediately before dispatch.
        $this->maintenance->assertAllowed();
        $lock->assertOwned();
        try {
            $receipt = $this->creator->create($target, $bytes);
        } catch (Throwable) {
            // Once dispatched, an unexpected adapter failure is conservatively ambiguous.
            $receipt = new CreateReceipt(CreateState::Unknown);
        }
        try {
            $this->journal->recordReceipt($objectId, $receipt);
        } catch (Throwable) {
            // Preserve durable intent: no delete, fabricated receipt or retry.
            throw new BackfillSafetyException(SafetyError::PublicationUnknown);
        }

        return $receipt;
    }
}
