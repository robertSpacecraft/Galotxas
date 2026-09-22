<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('profile_declarations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('actor_user_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->foreignId('subject_player_id')
                ->nullable()
                ->constrained('players')
                ->nullOnDelete();
            $table->enum('declaration_kind', [
                'account_profile_accuracy',
                'birth_date_accuracy',
            ]);
            $table->string('notice_id', 80);
            $table->string('notice_version', 20);
            $table->dateTime('declared_at');

            $table->index(
                ['actor_user_id', 'declaration_kind', 'notice_id', 'notice_version'],
                'profile_declarations_actor_recognition_index'
            );
            $table->index(
                ['subject_player_id', 'declaration_kind', 'declared_at'],
                'profile_declarations_subject_history_index'
            );
        });
    }

    public function down(): void
    {
        throw new RuntimeException('La evidencia de declaraciones usa una migración forward-only.');
    }
};
