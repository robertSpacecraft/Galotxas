<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('game_matches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('round_id')->constrained()->cascadeOnDelete();
            $table->foreignId('venue_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('home_entry_id')->constrained('category_entries')->cascadeOnDelete();
            $table->foreignId('away_entry_id')->constrained('category_entries')->cascadeOnDelete();
            $table->dateTime('scheduled_date')->nullable();
            $table->integer('home_score')->nullable();
            $table->integer('away_score')->nullable();
            $table->enum('status', ['scheduled', 'submitted', 'validated', 'postponed', 'cancelled'])->default('scheduled');
            $table->foreignId('submitted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('validated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('game_matches');
    }
};
