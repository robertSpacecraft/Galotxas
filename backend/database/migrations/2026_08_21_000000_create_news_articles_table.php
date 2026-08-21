<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('news_articles', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('slug')->unique();
            $table->string('excerpt', 500);
            $table->text('body');
            $table->string('image_key', 64)->nullable()->unique();
            $table->unsignedSmallInteger('image_width')->nullable();
            $table->unsignedSmallInteger('image_height')->nullable();
            $table->string('image_alt')->nullable();
            $table->string('image_credit')->nullable();
            $table->string('image_source', 500)->nullable();
            $table->dateTime('image_rights_confirmed_at')->nullable();
            $table->foreignId('image_rights_confirmed_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->enum('status', ['draft', 'published'])->default('draft');
            $table->dateTime('published_at')->nullable();
            $table->string('seo_title')->nullable();
            $table->string('seo_description', 500)->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(
                ['status', 'published_at', 'id'],
                'news_articles_publication_order_index'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('news_articles');
    }
};
