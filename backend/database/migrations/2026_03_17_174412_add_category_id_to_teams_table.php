<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('teams', function (Blueprint $table) {
            $table->foreignId('category_id')
                ->nullable()
                ->after('id')
                ->constrained()
                ->nullOnDelete();
        });

        // Backfill para equipos existentes:
        // si un equipo ya está usado por una category_entry, tomamos esa category.
        DB::statement("
            UPDATE teams t
            INNER JOIN category_entries ce ON ce.team_id = t.id
            SET t.category_id = ce.category_id
            WHERE t.category_id IS NULL
        ");
    }

    public function down(): void
    {
        Schema::table('teams', function (Blueprint $table) {
            $table->dropConstrainedForeignId('category_id');
        });
    }
};
