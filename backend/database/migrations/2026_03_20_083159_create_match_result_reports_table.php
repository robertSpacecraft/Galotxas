<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('match_result_reports', function (Blueprint $table) {
            $table->id();

            // Relación con partido
            $table->foreignId('game_match_id')
                ->constrained()
                ->cascadeOnDelete();

            // Quién reporta
            $table->foreignId('user_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->foreignId('player_id')
                ->constrained()
                ->cascadeOnDelete();

            // Lado del reporte
            $table->enum('side', ['home', 'away']);

            // Resultado reportado
            $table->unsignedTinyInteger('home_score');
            $table->unsignedTinyInteger('away_score');

            // Estado del reporte
            $table->enum('status', ['submitted', 'validated', 'conflict'])
                ->default('submitted');

            // Comentario opcional
            $table->text('comment')->nullable();

            $table->timestamps();

            // Restricción clave: un reporte por lado y partido
            $table->unique(['game_match_id', 'side']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('match_result_reports');
    }
};
