<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('educational_activities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('educational_center_id')
                ->constrained('educational_centers')
                ->restrictOnDelete();
            $table->foreignId('school_location_id')
                ->nullable()
                ->constrained('school_locations')
                ->restrictOnDelete();
            $table->string('name');
            $table->date('activity_date');
            $table->time('starts_at')->nullable();
            $table->time('ends_at')->nullable();
            $table->unsignedSmallInteger('expected_students')->nullable();
            $table->string('status', 20)->default('planned');
            $table->text('admin_notes')->nullable();
            $table->timestamps();

            $table->index(
                ['educational_center_id', 'activity_date'],
                'educational_activities_center_date_index'
            );
            $table->index(
                ['status', 'activity_date'],
                'educational_activities_status_date_index'
            );
            $table->index(
                ['school_location_id', 'activity_date'],
                'educational_activities_location_date_index'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('educational_activities');
    }
};
