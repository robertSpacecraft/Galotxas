<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('school_levels', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_program_id')
                ->constrained('school_programs')
                ->restrictOnDelete();
            $table->string('name');
            $table->unsignedTinyInteger('minimum_age')->nullable();
            $table->unsignedTinyInteger('maximum_age')->nullable();
            $table->boolean('is_active')->default(false);
            $table->boolean('is_public')->default(false);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(
                ['school_program_id', 'sort_order'],
                'school_levels_program_order_index'
            );
            $table->index(
                ['is_active', 'is_public'],
                'school_levels_active_public_index'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('school_levels');
    }
};
