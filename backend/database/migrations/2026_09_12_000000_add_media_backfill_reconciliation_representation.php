<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('media_backfill_reconciliation_events', function (Blueprint $table) {
            $table->uuid('event_id')->primary();
            $table->uuid('attempt_id');
            $table->uuid('run_id');
            $table->unsignedBigInteger('item_id')->nullable();
            $table->string('event_type', 40);
            $table->unsignedSmallInteger('evidence_version');
            $table->char('storage_identity_hash', 64);
            $table->string('backend_mode', 16);
            $table->string('code_revision', 64)->nullable();
            $table->char('evidence_sha256', 64);
            $table->json('evidence_json');
            $table->dateTime('created_at');

            $table->foreign('run_id', 'mb_recon_events_run_fk')
                ->references('run_id')->on('media_backfill_runs')
                ->restrictOnDelete()->restrictOnUpdate();
            $table->foreign('item_id', 'mb_recon_events_item_fk')
                ->references('id')->on('media_backfill_items')
                ->restrictOnDelete()->restrictOnUpdate();
            $table->index(['attempt_id', 'created_at'], 'mb_recon_attempt_created_idx');
            $table->index(['run_id', 'created_at'], 'mb_recon_run_created_idx');
            $table->index(['item_id', 'created_at'], 'mb_recon_item_created_idx');
        });

        Schema::table('media_backfill_runs', function (Blueprint $table) {
            $table->uuid('reconciliation_event_id')->nullable()->default(null);
            $table->foreign('reconciliation_event_id', 'mb_runs_recon_event_fk')
                ->references('event_id')->on('media_backfill_reconciliation_events')
                ->restrictOnDelete()->restrictOnUpdate();
        });

        Schema::table('media_backfill_items', function (Blueprint $table) {
            $table->string('reconciliation_result', 32)->nullable()->default(null);
            $table->uuid('reconciliation_event_id')->nullable()->default(null);
            $table->foreign('reconciliation_event_id', 'mb_items_recon_event_fk')
                ->references('event_id')->on('media_backfill_reconciliation_events')
                ->restrictOnDelete()->restrictOnUpdate();
            $table->index(['phase', 'reconciliation_result'], 'mb_items_phase_recon_idx');
        });

        Schema::table('media_backfill_objects', function (Blueprint $table) {
            $table->string('reconciliation_resolution', 32)->nullable()->default(null);
            $table->uuid('reconciliation_event_id')->nullable()->default(null);
            $table->foreign('reconciliation_event_id', 'mb_objects_recon_event_fk')
                ->references('event_id')->on('media_backfill_reconciliation_events')
                ->restrictOnDelete()->restrictOnUpdate();
            $table->index(['write_state', 'reconciliation_resolution'], 'mb_objects_write_recon_idx');
            $table->index(['cleanup_state', 'reconciliation_resolution'], 'mb_objects_cleanup_recon_idx');
        });
    }

    public function down(): void
    {
        $projectionColumns = [
            ['media_backfill_runs', 'reconciliation_event_id'],
            ['media_backfill_items', 'reconciliation_result'],
            ['media_backfill_items', 'reconciliation_event_id'],
            ['media_backfill_objects', 'reconciliation_resolution'],
            ['media_backfill_objects', 'reconciliation_event_id'],
        ];

        foreach ($projectionColumns as [$table, $column]) {
            if (Schema::hasTable($table)
                && Schema::hasColumn($table, $column)
                && DB::table($table)->whereNotNull($column)->exists()) {
                throw new RuntimeException('Cannot roll back durable media backfill reconciliation provenance.');
            }
        }

        if (Schema::hasTable('media_backfill_reconciliation_events')
            && DB::table('media_backfill_reconciliation_events')->exists()) {
            throw new RuntimeException('Cannot roll back durable media backfill reconciliation provenance.');
        }

        Schema::table('media_backfill_objects', function (Blueprint $table) {
            $table->dropForeign('mb_objects_recon_event_fk');
            $table->dropIndex('mb_objects_write_recon_idx');
            $table->dropIndex('mb_objects_cleanup_recon_idx');
            $table->dropColumn(['reconciliation_resolution', 'reconciliation_event_id']);
        });

        Schema::table('media_backfill_items', function (Blueprint $table) {
            $table->dropForeign('mb_items_recon_event_fk');
            $table->dropIndex('mb_items_phase_recon_idx');
            $table->dropColumn(['reconciliation_result', 'reconciliation_event_id']);
        });

        Schema::table('media_backfill_runs', function (Blueprint $table) {
            $table->dropForeign('mb_runs_recon_event_fk');
            $table->dropColumn('reconciliation_event_id');
        });

        Schema::dropIfExists('media_backfill_reconciliation_events');
    }
};
