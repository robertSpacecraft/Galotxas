<?php

use App\Enums\ChampionshipRegistrationStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('championships', function (Blueprint $table) {
            $table->string('registration_status')
                ->default(ChampionshipRegistrationStatus::CLOSED->value)
                ->after('status');

            $table->timestamp('registration_starts_at')
                ->nullable()
                ->after('registration_status');

            $table->timestamp('registration_ends_at')
                ->nullable()
                ->after('registration_starts_at');
        });
    }

    public function down(): void
    {
        Schema::table('championships', function (Blueprint $table) {
            $table->dropColumn([
                'registration_status',
                'registration_starts_at',
                'registration_ends_at',
            ]);
        });
    }
};
