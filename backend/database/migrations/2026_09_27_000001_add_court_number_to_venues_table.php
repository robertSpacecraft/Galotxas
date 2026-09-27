<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement(<<<'SQL'
            ALTER TABLE `venues`
                ADD COLUMN `court_number` INT UNSIGNED NULL AFTER `id`,
                ADD CONSTRAINT `venues_court_number_positive`
                    CHECK (`court_number` IS NULL OR `court_number` > 0),
                ADD UNIQUE INDEX `venues_court_number_unique` (`court_number`)
            SQL);
    }

    public function down(): void
    {
        DB::statement(<<<'SQL'
            ALTER TABLE `venues`
                DROP CONSTRAINT `venues_court_number_positive`,
                DROP INDEX `venues_court_number_unique`,
                DROP COLUMN `court_number`
            SQL);
    }
};
