<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('categories', function (Blueprint $table) {
            $table->string('gender')->nullable()->after('level');
        });

        DB::table('categories')
            ->where('category_type', 'open')
            ->update(['gender' => 'male']);

        DB::table('categories')
            ->where('category_type', 'female')
            ->update(['gender' => 'female']);

        DB::table('categories')
            ->where('category_type', 'mixed')
            ->update(['gender' => 'mixed']);

        Schema::table('categories', function (Blueprint $table) {
            $table->dropColumn('category_type');
        });

        Schema::table('categories', function (Blueprint $table) {
            $table->string('gender')->nullable(false)->change();
        });
    }

    public function down(): void
    {
        Schema::table('categories', function (Blueprint $table) {
            $table->string('category_type')->nullable()->after('level');
        });

        DB::table('categories')
            ->where('gender', 'male')
            ->update(['category_type' => 'open']);

        DB::table('categories')
            ->where('gender', 'female')
            ->update(['category_type' => 'female']);

        DB::table('categories')
            ->where('gender', 'mixed')
            ->update(['category_type' => 'mixed']);

        Schema::table('categories', function (Blueprint $table) {
            $table->dropColumn('gender');
        });

        Schema::table('categories', function (Blueprint $table) {
            $table->string('category_type')->nullable(false)->change();
        });
    }
};
