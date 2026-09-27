<?php

namespace App\Services;

use App\Models\Venue;
use Illuminate\Support\Facades\DB;
use LogicException;

class VenueDeletionService
{
    public function deleteIfUnused(Venue $venue): bool
    {
        $connection = DB::connection();

        if ($connection->transactionLevel() !== 0) {
            throw new LogicException('La eliminación de pistas requiere una transacción raíz propia.');
        }

        $connection->statement('SET TRANSACTION ISOLATION LEVEL READ COMMITTED');

        return $connection->transaction(function () use ($venue): bool {
            /** @var Venue $lockedVenue */
            $lockedVenue = Venue::query()
                ->whereKey($venue->id)
                ->lockForUpdate()
                ->firstOrFail();

            // READ COMMITTED makes these post-lock checks current without taking
            // child row locks in the inverse Venue -> GameMatch order.
            if ($lockedVenue->isInUse()) {
                return false;
            }

            $lockedVenue->delete();

            return true;
        });
    }
}
