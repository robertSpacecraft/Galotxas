<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('seasons', function (Blueprint $table) {
            $table->boolean('is_public')->default(false)->after('status');
        });

        Schema::table('championships', function (Blueprint $table) {
            $table->boolean('is_public')->default(false)->after('status');
        });

        Schema::table('categories', function (Blueprint $table) {
            $table->boolean('is_public')->default(false)->after('status');
        });

        // Preserve the public reachability that every existing record had before this flag existed.
        DB::table('seasons')->update(['is_public' => true]);
        DB::table('championships')->update(['is_public' => true]);
        DB::table('categories')->update(['is_public' => true]);
    }

    public function down(): void
    {
        Schema::table('categories', function (Blueprint $table) {
            $table->dropColumn('is_public');
        });

        Schema::table('championships', function (Blueprint $table) {
            $table->dropColumn('is_public');
        });

        Schema::table('seasons', function (Blueprint $table) {
            $table->dropColumn('is_public');
        });
    }
};
