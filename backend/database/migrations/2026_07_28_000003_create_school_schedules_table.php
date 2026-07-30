<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('school_schedules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_level_id')
                ->constrained('school_levels')
                ->restrictOnDelete();
            $table->foreignId('school_location_id')
                ->constrained('school_locations')
                ->restrictOnDelete();
            $table->unsignedTinyInteger('day_of_week');
            $table->time('starts_at');
            $table->time('ends_at');
            $table->boolean('is_active')->default(false);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(
                [
                    'school_level_id',
                    'school_location_id',
                    'day_of_week',
                    'starts_at',
                    'ends_at',
                ],
                'school_schedules_exact_slot_unique'
            );
            $table->index(
                ['school_level_id', 'sort_order'],
                'school_schedules_level_order_index'
            );
            $table->index('school_location_id', 'school_schedules_location_index');
            $table->index(
                ['is_active', 'day_of_week', 'sort_order'],
                'school_schedules_active_day_order_index'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('school_schedules');
    }
};
