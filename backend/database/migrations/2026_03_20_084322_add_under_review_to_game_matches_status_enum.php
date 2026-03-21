<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("
            ALTER TABLE game_matches
            MODIFY COLUMN status ENUM(
                'scheduled',
                'submitted',
                'validated',
                'under_review',
                'postponed',
                'cancelled'
            ) NOT NULL DEFAULT 'scheduled'
        ");
    }

    public function down(): void
    {
        DB::statement("
            ALTER TABLE game_matches
            MODIFY COLUMN status ENUM(
                'scheduled',
                'submitted',
                'validated',
                'postponed',
                'cancelled'
            ) NOT NULL DEFAULT 'scheduled'
        ");
    }
};
