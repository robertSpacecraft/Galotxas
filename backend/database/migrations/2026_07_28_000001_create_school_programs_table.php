<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('school_programs', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->boolean('is_public')->default(false);
            $table->boolean('enrollments_open')->default(false);
            $table->foreignId('default_school_location_id')
                ->nullable()
                ->constrained('school_locations')
                ->restrictOnDelete();
            $table->string('contact_phone', 50)->nullable();
            $table->string('contact_email')->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);

            // MariaDB has no partial unique indexes. NULL values remain repeatable, while
            // every public program generates the same guarded slot.
            $table->unsignedTinyInteger('public_slot')
                ->nullable()
                ->storedAs('if(`is_public`, 1, null)');

            $table->timestamps();

            $table->unique('public_slot', 'school_programs_one_public_unique');
            $table->index(['is_public', 'sort_order'], 'school_programs_public_order_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('school_programs');
    }
};
