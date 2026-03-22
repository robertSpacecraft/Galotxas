<?php

use App\Enums\MatchRescheduleRequestStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('match_reschedule_requests', function (Blueprint $table) {
            $table->id();

            $table->foreignId('game_match_id')
                ->constrained('game_matches')
                ->cascadeOnDelete();

            $table->foreignId('user_id')
                ->constrained('users')
                ->cascadeOnDelete();

            $table->foreignId('player_id')
                ->constrained('players')
                ->cascadeOnDelete();

            $table->string('side', 10);

            $table->dateTime('requested_scheduled_date');
            $table->foreignId('requested_venue_id')
                ->constrained('venues')
                ->restrictOnDelete();

            $table->string('status')->default(MatchRescheduleRequestStatus::SUBMITTED->value);
            $table->text('comment')->nullable();

            $table->timestamps();

            $table->unique(['game_match_id', 'side'], 'match_reschedule_requests_match_side_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('match_reschedule_requests');
    }
};
