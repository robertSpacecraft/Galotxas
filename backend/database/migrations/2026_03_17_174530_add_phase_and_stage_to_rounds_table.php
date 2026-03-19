<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('rounds', function (Blueprint $table) {
            $table->string('phase')->nullable()->after('type');
            $table->string('stage')->nullable()->after('phase');
        });

        // Compatibilidad con lo actual:
        // las rondas existentes con type=league pasan a phase=league y stage=matchday
        DB::table('rounds')
            ->where('type', 'league')
            ->update([
                'phase' => 'league',
                'stage' => 'matchday',
            ]);
    }

    public function down(): void
    {
        Schema::table('rounds', function (Blueprint $table) {
            $table->dropColumn(['phase', 'stage']);
        });
    }
};
