<?php

use Carbon\CarbonImmutable;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('school_programs', function (Blueprint $table) {
            $table->text('public_description')->nullable()->after('name');
            $table->text('enrollment_information')->nullable()->after('public_description');
        });

        Schema::table('school_enrollments', function (Blueprint $table) {
            $table->string('participant_name')->nullable()->change();
            $table->date('participant_birth_date')->nullable()->change();
            $table->string('contact_phone', 50)->nullable()->change();
            $table->string('contact_email')->nullable()->change();

            $table->string('privacy_notice_id', 80)
                ->nullable()
                ->before('privacy_notice_version');
            $table->dateTime('corrected_at')->nullable()->after('admin_notes');
            $table->foreignId('corrected_by')
                ->nullable()
                ->after('corrected_at')
                ->constrained('users')
                ->nullOnDelete();
            $table->foreignId('activated_by')
                ->nullable()
                ->after('activated_at')
                ->constrained('users')
                ->nullOnDelete();
            $table->foreignId('rejected_by')
                ->nullable()
                ->after('rejected_at')
                ->constrained('users')
                ->nullOnDelete();
            $table->foreignId('withdrawn_by')
                ->nullable()
                ->after('withdrawn_at')
                ->constrained('users')
                ->nullOnDelete();
            $table->dateTime('retention_until')->nullable()->after('privacy_acknowledged_at');
            $table->boolean('retention_hold')->default(false)->after('retention_until');
            $table->string('retention_hold_reason', 500)->nullable();
            $table->dateTime('retention_hold_placed_at')->nullable();
            $table->foreignId('retention_hold_placed_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->dateTime('retention_hold_released_at')->nullable();
            $table->foreignId('retention_hold_released_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->dateTime('anonymized_at')->nullable();

            $table->index(
                ['status', 'retention_until', 'retention_hold'],
                'school_enrollments_retention_index'
            );
        });

        DB::table('school_enrollments')
            ->select(['id', 'status', 'requested_at', 'rejected_at', 'withdrawn_at', 'updated_at'])
            ->orderBy('id')
            ->chunkById(200, function ($enrollments): void {
                foreach ($enrollments as $enrollment) {
                    $retentionUntil = match ($enrollment->status) {
                        'pending' => CarbonImmutable::parse($enrollment->requested_at)
                            ->addMonthsNoOverflow(6),
                        'rejected' => CarbonImmutable::parse(
                            $enrollment->rejected_at ?? $enrollment->updated_at
                        )->addMonthsNoOverflow(6),
                        'withdrawn' => CarbonImmutable::parse(
                            $enrollment->withdrawn_at ?? $enrollment->updated_at
                        )->addYearsNoOverflow(2),
                        default => null,
                    };

                    if ($retentionUntil !== null) {
                        DB::table('school_enrollments')
                            ->where('id', $enrollment->id)
                            ->update(['retention_until' => $retentionUntil]);
                    }
                }
            });
    }

    public function down(): void
    {
        Schema::table('school_enrollments', function (Blueprint $table) {
            $table->dropForeign(['corrected_by']);
            $table->dropForeign(['activated_by']);
            $table->dropForeign(['rejected_by']);
            $table->dropForeign(['withdrawn_by']);
            $table->dropForeign(['retention_hold_placed_by']);
            $table->dropForeign(['retention_hold_released_by']);
            $table->dropIndex('school_enrollments_retention_index');
            $table->dropColumn([
                'privacy_notice_id',
                'corrected_at',
                'corrected_by',
                'activated_by',
                'rejected_by',
                'withdrawn_by',
                'retention_until',
                'retention_hold',
                'retention_hold_reason',
                'retention_hold_placed_at',
                'retention_hold_placed_by',
                'retention_hold_released_at',
                'retention_hold_released_by',
                'anonymized_at',
            ]);
        });

        DB::table('school_enrollments')
            ->whereNull('participant_name')
            ->update(['participant_name' => '[eliminado]']);
        DB::table('school_enrollments')
            ->whereNull('participant_birth_date')
            ->update(['participant_birth_date' => '1900-01-01']);
        DB::table('school_enrollments')
            ->whereNull('contact_phone')
            ->update(['contact_phone' => '[eliminado]']);
        DB::table('school_enrollments')
            ->whereNull('contact_email')
            ->update(['contact_email' => 'eliminado@example.invalid']);

        Schema::table('school_enrollments', function (Blueprint $table) {
            $table->string('participant_name')->nullable(false)->change();
            $table->date('participant_birth_date')->nullable(false)->change();
            $table->string('contact_phone', 50)->nullable(false)->change();
            $table->string('contact_email')->nullable(false)->change();
        });

        Schema::table('school_programs', function (Blueprint $table) {
            $table->dropColumn(['public_description', 'enrollment_information']);
        });
    }
};
