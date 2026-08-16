<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sponsors', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('logo_key', 64)->unique();
            $table->unsignedSmallInteger('logo_width');
            $table->unsignedSmallInteger('logo_height');
            $table->string('website_url', 2048)->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(false);
            $table->dateTime('starts_at')->nullable();
            $table->dateTime('ends_at')->nullable();
            $table->timestamps();

            $table->index(['is_active', 'sort_order'], 'sponsors_active_order_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sponsors');
    }
};
