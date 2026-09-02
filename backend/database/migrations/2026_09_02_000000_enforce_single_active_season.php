<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const ALLOWED_STATUSES = [
        'planned',
        'active',
        'finished',
        'cancelled',
    ];

    public function up(): void
    {
        $invalidStatuses = DB::table('seasons')
            ->whereNull('status')
            ->orWhereRaw(
                'BINARY `status` NOT IN (?, ?, ?, ?)',
                self::ALLOWED_STATUSES
            )
            ->distinct()
            ->pluck('status')
            ->map(fn ($status): string => $status === null ? 'NULL' : (string) $status)
            ->sort()
            ->values();

        if ($invalidStatuses->isNotEmpty()) {
            throw new RuntimeException(sprintf(
                'No se puede aplicar la invariancia de temporadas: existen estados incompatibles (%s).',
                $invalidStatuses->implode(', ')
            ));
        }

        $activeSeasons = DB::table('seasons')
            ->where('status', 'active')
            ->count();

        if ($activeSeasons > 1) {
            throw new RuntimeException(sprintf(
                'No se puede aplicar la invariancia de temporadas: existen %d temporadas activas.',
                $activeSeasons
            ));
        }

        // MariaDB has no partial unique indexes. NULL values remain repeatable, while
        // every active season generates the same guarded slot. One ALTER keeps the
        // default and exclusivity change indivisible at schema level.
        DB::statement(<<<'SQL'
            ALTER TABLE `seasons`
                MODIFY COLUMN `status` VARCHAR(255) NOT NULL DEFAULT 'planned',
                ADD COLUMN `active_slot` TINYINT UNSIGNED
                    GENERATED ALWAYS AS (IF(`status` = 'active', 1, NULL)) STORED
                    AFTER `status`,
                ADD UNIQUE INDEX `seasons_one_active_unique` (`active_slot`)
            SQL);
    }

    public function down(): void
    {
        DB::statement(<<<'SQL'
            ALTER TABLE `seasons`
                DROP INDEX `seasons_one_active_unique`,
                DROP COLUMN `active_slot`,
                MODIFY COLUMN `status` VARCHAR(255) NOT NULL DEFAULT 'pending'
            SQL);
    }
};
