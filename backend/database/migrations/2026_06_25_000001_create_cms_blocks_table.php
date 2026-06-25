<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cms_blocks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cms_page_id')->constrained('cms_pages')->cascadeOnDelete();
            $table->enum('type', [
                'heading',
                'text',
                'list',
                'image',
                'gallery',
                'button',
                'document_link',
            ]);
            $table->unsignedInteger('sort_order')->default(0);
            $table->json('data');
            $table->timestamps();

            $table->index(['cms_page_id', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cms_blocks');
    }
};
