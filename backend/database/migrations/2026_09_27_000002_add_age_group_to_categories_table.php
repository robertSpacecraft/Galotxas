<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement(<<<'SQL'
            ALTER TABLE `categories`
                ADD COLUMN `age_group` VARCHAR(16) NULL AFTER `gender`,
                ADD CONSTRAINT `categories_age_group_allowed`
                    CHECK (`age_group` IS NULL OR `age_group` IN ('open', 'youth'))
            SQL);
    }

    public function down(): void
    {
        DB::statement(<<<'SQL'
            ALTER TABLE `categories`
                DROP CONSTRAINT `categories_age_group_allowed`,
                DROP COLUMN `age_group`
            SQL);
    }
};
