<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cms_navigation_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cms_page_id')->constrained()->cascadeOnDelete();
            $table->enum('slot', ['club']);
            $table->string('label', 80);
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(false);
            $table->timestamps();

            $table->unique(['cms_page_id', 'slot']);
            $table->index(['slot', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cms_navigation_items');
    }
};
