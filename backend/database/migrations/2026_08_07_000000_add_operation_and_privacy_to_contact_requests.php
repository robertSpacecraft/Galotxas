<?php

use App\Enums\ContactNotificationStatus;
use Carbon\CarbonImmutable;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('contact_requests', function (Blueprint $table) {
            $table->string('name', 120)->nullable()->change();
            $table->string('email', 254)->nullable()->change();
            $table->string('subject', 200)->nullable()->change();
            $table->text('message')->nullable()->change();
            $table->char('ip_hash', 64)->nullable()->change();

            $table->string('privacy_notice_id', 80)->nullable()->after('consent_at');
            $table->string('privacy_notice_version', 20)->nullable()->after('privacy_notice_id');
            $table->dateTime('closed_at')->nullable()->after('status');
            $table->dateTime('retention_until')->nullable()->after('closed_at');
            $table->string('notification_status', 20)
                ->default(ContactNotificationStatus::NOT_REQUESTED->value)
                ->after('privacy_notice_version');
            $table->unsignedTinyInteger('notification_attempt_count')->default(0);
            $table->dateTime('notification_attempted_at')->nullable();
            $table->dateTime('notification_sent_at')->nullable();
            $table->dateTime('notification_failed_at')->nullable();
            $table->string('notification_failure_code', 80)->nullable();
            $table->dateTime('ip_hash_expires_at')->nullable()->after('ip_hash');
            $table->boolean('retention_hold')->default(false);
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
                ['notification_status', 'created_at'],
                'contact_requests_notification_created_index'
            );
            $table->index(
                ['status', 'retention_until', 'retention_hold'],
                'contact_requests_retention_index'
            );
            $table->index('ip_hash_expires_at', 'contact_requests_ip_hash_expiry_index');
        });

        Schema::create('contact_request_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('contact_request_id')
                ->constrained('contact_requests')
                ->restrictOnDelete();
            $table->string('type', 48);
            $table->foreignId('actor_user_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->dateTime('occurred_at');
            $table->json('metadata')->nullable();

            $table->index(
                ['contact_request_id', 'occurred_at'],
                'contact_request_events_history_index'
            );
        });

        DB::table('contact_requests')
            ->select(['id', 'status', 'created_at', 'updated_at'])
            ->orderBy('id')
            ->chunkById(200, function ($requests): void {
                foreach ($requests as $request) {
                    $createdAt = CarbonImmutable::parse($request->created_at);
                    $values = [
                        'ip_hash_expires_at' => $createdAt->addDays(30),
                    ];

                    if ($request->status === 'closed') {
                        $closedAt = CarbonImmutable::parse($request->updated_at);
                        $values['closed_at'] = $closedAt;
                        $values['retention_until'] = $closedAt->addMonthsNoOverflow(12);
                    }

                    DB::table('contact_requests')
                        ->where('id', $request->id)
                        ->update($values);
                }
            });
    }

    public function down(): void
    {
        Schema::dropIfExists('contact_request_events');

        Schema::table('contact_requests', function (Blueprint $table) {
            $table->dropForeign(['retention_hold_placed_by']);
            $table->dropForeign(['retention_hold_released_by']);
            $table->dropIndex('contact_requests_notification_created_index');
            $table->dropIndex('contact_requests_retention_index');
            $table->dropIndex('contact_requests_ip_hash_expiry_index');
            $table->dropColumn([
                'privacy_notice_id',
                'privacy_notice_version',
                'closed_at',
                'retention_until',
                'notification_status',
                'notification_attempt_count',
                'notification_attempted_at',
                'notification_sent_at',
                'notification_failed_at',
                'notification_failure_code',
                'ip_hash_expires_at',
                'retention_hold',
                'retention_hold_reason',
                'retention_hold_placed_at',
                'retention_hold_placed_by',
                'retention_hold_released_at',
                'retention_hold_released_by',
                'anonymized_at',
            ]);
        });

        DB::table('contact_requests')->whereNull('name')->update(['name' => '[eliminado]']);
        DB::table('contact_requests')->whereNull('email')->update(['email' => 'eliminado@example.invalid']);
        DB::table('contact_requests')->whereNull('subject')->update(['subject' => '[eliminado]']);
        DB::table('contact_requests')->whereNull('message')->update(['message' => '[eliminado]']);
        DB::table('contact_requests')->whereNull('ip_hash')->update(['ip_hash' => str_repeat('0', 64)]);

        Schema::table('contact_requests', function (Blueprint $table) {
            $table->string('name', 120)->nullable(false)->change();
            $table->string('email', 254)->nullable(false)->change();
            $table->string('subject', 200)->nullable(false)->change();
            $table->text('message')->nullable(false)->change();
            $table->char('ip_hash', 64)->nullable(false)->change();
        });
    }
};
