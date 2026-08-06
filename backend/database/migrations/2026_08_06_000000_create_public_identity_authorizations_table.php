<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('school_enrollments', function (Blueprint $table) {
            $table->string('privacy_notice_version', 20)->nullable();
            $table->dateTime('privacy_acknowledged_at')->nullable();
        });

        Schema::create('public_identity_authorizations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_enrollment_id')
                ->nullable()
                ->constrained('school_enrollments')
                ->restrictOnDelete();
            $table->foreignId('player_id')
                ->nullable()
                ->constrained()
                ->restrictOnDelete();
            $table->enum('scope', ['public_competition_identity']);
            $table->enum('mode', ['alias', 'name_initial', 'anonymous']);
            $table->enum('state', ['pending', 'approved', 'denied', 'revoked', 'expired'])
                ->default('pending');
            $table->unsignedTinyInteger('approval_slot')->nullable();
            $table->string('guardian_email', 254);
            $table->string('guardian_name');
            $table->string('guardian_relationship', 100);
            $table->dateTime('guardian_authority_declared_at');
            $table->string('notice_id', 80);
            $table->string('notice_version', 20);
            $table->dateTime('requested_at');
            $table->dateTime('guardian_confirmed_at')->nullable();
            $table->dateTime('guardian_denied_at')->nullable();
            $table->dateTime('minor_assent_recorded_at')->nullable();
            $table->foreignId('minor_assent_recorded_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->dateTime('reviewed_at')->nullable();
            $table->foreignId('reviewed_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->dateTime('approved_at')->nullable();
            $table->dateTime('denied_at')->nullable();
            $table->dateTime('revoked_at')->nullable();
            $table->foreignId('revoked_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->text('private_reason')->nullable();
            $table->dateTime('expires_at')->nullable();
            $table->char('confirmation_token_hash', 64)->nullable()->unique();
            $table->dateTime('confirmation_token_expires_at')->nullable();
            $table->dateTime('confirmation_token_used_at')->nullable();
            $table->timestamps();

            $table->unique(
                ['player_id', 'scope', 'approval_slot'],
                'public_identity_authorizations_effective_unique'
            );
            $table->index(
                ['state', 'requested_at'],
                'public_identity_authorizations_state_requested_index'
            );
            $table->index(
                ['school_enrollment_id', 'scope'],
                'public_identity_authorizations_enrollment_scope_index'
            );
        });

        Schema::create('public_identity_authorization_events', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('public_identity_authorization_id');
            $table->string('type', 48);
            $table->foreignId('actor_user_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->dateTime('occurred_at');
            $table->json('metadata')->nullable();

            $table->foreign(
                'public_identity_authorization_id',
                'pia_events_authorization_foreign'
            )
                ->references('id')
                ->on('public_identity_authorizations')
                ->restrictOnDelete();

            $table->index(
                ['public_identity_authorization_id', 'occurred_at'],
                'public_identity_authorization_events_history_index'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('public_identity_authorization_events');
        Schema::dropIfExists('public_identity_authorizations');

        Schema::table('school_enrollments', function (Blueprint $table) {
            $table->dropColumn([
                'privacy_notice_version',
                'privacy_acknowledged_at',
            ]);
        });
    }
};
