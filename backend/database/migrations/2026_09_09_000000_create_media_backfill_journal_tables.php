<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('media_backfill_runs', function (Blueprint $table) {
            $table->uuid('run_id')->primary();
            $table->string('mode', 16);
            $table->string('state', 24);
            $table->json('options_json');
            $table->char('storage_identity_hash', 64);
            $table->char('code_revision', 40)->nullable();
            $table->json('upper_bounds_json')->nullable();
            $table->json('checkpoints_json')->nullable();
            $table->json('summary_json')->nullable();
            $table->dateTime('started_at');
            $table->dateTime('heartbeat_at')->nullable();
            $table->dateTime('finished_at')->nullable();
            $table->string('error_code', 64)->nullable();
            $table->timestamps();
            $table->index(['state', 'started_at']);
        });

        Schema::create('media_backfill_items', function (Blueprint $table) {
            $table->id();
            $table->uuid('run_id');
            $table->foreign('run_id')->references('run_id')->on('media_backfill_runs')->restrictOnDelete()->restrictOnUpdate();
            $table->string('domain', 24);
            $table->unsignedBigInteger('entity_id');
            $table->string('master_key')->nullable();
            $table->char('master_key_hash', 64);
            $table->string('reference_sample', 160)->nullable();
            $table->string('manifest_key')->nullable();
            $table->string('preflight_classification', 32);
            $table->json('reason_codes_json');
            $table->string('phase', 24);
            $table->string('apply_result', 32)->nullable();
            $table->char('source_sha256', 64)->nullable();
            $table->char('candidate_manifest_sha256', 64)->nullable();
            $table->json('candidate_manifest_json')->nullable();
            $table->dateTime('inspected_at')->nullable();
            $table->dateTime('revalidated_at')->nullable();
            $table->dateTime('finished_at')->nullable();
            $table->timestamps();
            $table->unique(['run_id', 'domain', 'entity_id']);
            $table->index(['run_id', 'phase']);
            $table->index(['master_key_hash', 'phase']);
        });

        Schema::create('media_backfill_objects', function (Blueprint $table) {
            $table->id();
            $table->foreignId('item_id')->constrained('media_backfill_items')->restrictOnDelete()->restrictOnUpdate();
            $table->string('object_key');
            $table->string('kind', 16);
            $table->char('expected_sha256', 64);
            $table->unsignedBigInteger('expected_size');
            $table->string('mime_type', 64);
            $table->string('write_state', 16);
            $table->string('create_state', 16)->nullable();
            $table->string('cleanup_state', 16);
            $table->string('etag', 255)->nullable();
            $table->string('version_id', 1024)->nullable();
            $table->dateTime('write_intent_at')->nullable();
            $table->dateTime('write_confirmed_at')->nullable();
            $table->dateTime('cleanup_attempted_at')->nullable();
            $table->dateTime('cleanup_finished_at')->nullable();
            $table->timestamps();
            $table->unique(['item_id', 'object_key']);
            $table->index(['cleanup_state', 'updated_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('media_backfill_objects');
        Schema::dropIfExists('media_backfill_items');
        Schema::dropIfExists('media_backfill_runs');
    }
};
