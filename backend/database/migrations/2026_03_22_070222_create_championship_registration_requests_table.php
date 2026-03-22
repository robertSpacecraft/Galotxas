<?php

use App\Enums\ChampionshipRegistrationPaymentStatus;
use App\Enums\ChampionshipRegistrationRequestStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('championship_registration_requests', function (Blueprint $table) {
            $table->id();

            $table->foreignId('championship_id')
                ->constrained('championships')
                ->cascadeOnDelete();

            $table->foreignId('user_id')
                ->constrained('users')
                ->cascadeOnDelete();

            $table->foreignId('player_id')
                ->constrained('players')
                ->cascadeOnDelete();

            $table->foreignId('suggested_category_id')
                ->nullable()
                ->constrained('categories')
                ->nullOnDelete();

            $table->string('status')
                ->default(ChampionshipRegistrationRequestStatus::PENDING->value);

            $table->string('payment_status')
                ->default(ChampionshipRegistrationPaymentStatus::PENDING->value);

            $table->text('comment')->nullable();

            $table->timestamps();

            $table->unique(
                ['championship_id', 'player_id'],
                'championship_registration_requests_championship_player_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('championship_registration_requests');
    }
};
