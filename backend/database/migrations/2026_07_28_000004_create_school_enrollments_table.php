<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('school_levels', function (Blueprint $table) {
            $table->unique(
                ['id', 'school_program_id'],
                'school_levels_id_program_unique'
            );
        });

        Schema::create('school_enrollments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_program_id')
                ->constrained('school_programs')
                ->restrictOnDelete();
            $table->unsignedBigInteger('school_level_id')->nullable();
            $table->foreignId('user_id')
                ->nullable()
                ->constrained()
                ->nullOnDelete();
            $table->string('participant_name');
            $table->date('participant_birth_date');
            $table->string('contact_phone', 50);
            $table->string('contact_email');
            $table->string('guardian_name')->nullable();
            $table->string('guardian_relationship', 100)->nullable();
            $table->string('status')->default('pending');
            $table->dateTime('requested_at');
            $table->dateTime('activated_at')->nullable();
            $table->dateTime('rejected_at')->nullable();
            $table->dateTime('withdrawn_at')->nullable();
            $table->text('admin_notes')->nullable();
            $table->timestamps();

            $table->foreign(
                ['school_level_id', 'school_program_id'],
                'school_enrollments_level_program_foreign'
            )
                ->references(['id', 'school_program_id'])
                ->on('school_levels')
                ->restrictOnDelete();
            $table->index(
                ['school_program_id', 'status'],
                'school_enrollments_program_status_index'
            );
            $table->index(
                ['school_level_id', 'status'],
                'school_enrollments_level_status_index'
            );
            $table->index(
                ['status', 'requested_at'],
                'school_enrollments_status_requested_index'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('school_enrollments');

        Schema::table('school_levels', function (Blueprint $table) {
            $table->dropUnique('school_levels_id_program_unique');
        });
    }
};
