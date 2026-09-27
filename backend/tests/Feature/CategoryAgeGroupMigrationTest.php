<?php

namespace Tests\Feature;

use App\Enums\CategoryAgeGroup;
use App\Enums\CategoryGender;
use App\Models\Category;
use App\Models\Championship;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class CategoryAgeGroupMigrationTest extends TestCase
{
    use DatabaseTruncation;

    protected function tearDown(): void
    {
        try {
            $this->truncateDatabaseTables();
        } finally {
            parent::tearDown();
        }
    }

    public function test_schema_has_nullable_age_group_with_named_allowed_values_check(): void
    {
        $this->assertTrue(Schema::hasColumn('categories', 'age_group'));

        $column = collect(DB::select(
            'SELECT DATA_TYPE, IS_NULLABLE FROM information_schema.COLUMNS '
            .'WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?',
            ['categories', 'age_group']
        ))->sole();
        $this->assertSame('varchar', $column->DATA_TYPE);
        $this->assertSame('YES', $column->IS_NULLABLE);

        $check = collect(DB::select(
            'SELECT CHECK_CLAUSE FROM information_schema.CHECK_CONSTRAINTS '
            .'WHERE CONSTRAINT_SCHEMA = DATABASE() AND TABLE_NAME = ? AND CONSTRAINT_NAME = ?',
            ['categories', 'categories_age_group_allowed']
        ))->sole();
        $this->assertStringContainsString('open', $check->CHECK_CLAUSE);
        $this->assertStringContainsString('youth', $check->CHECK_CLAUSE);
    }

    public function test_database_rejects_unsupported_non_null_age_group(): void
    {
        $category = Category::factory()->create([
            'age_group' => CategoryAgeGroup::OPEN->value,
        ]);

        try {
            DB::table('categories')
                ->where('id', $category->id)
                ->update(['age_group' => 'senior']);
            $this->fail('La restricción de base de datos debía rechazar el grupo no soportado.');
        } catch (QueryException $exception) {
            $this->assertStringContainsString('categories_age_group_allowed', $exception->getMessage());
        }

        $this->assertSame(CategoryAgeGroup::OPEN, $category->fresh()->age_group);
    }

    public function test_migration_preserves_existing_category_without_inferring_age_group(): void
    {
        $migration = require database_path(
            'migrations/2026_09_27_000002_add_age_group_to_categories_table.php'
        );
        $migration->down();

        try {
            $championship = Championship::factory()->create();
            $legacy = Category::query()->create([
                'championship_id' => $championship->id,
                'name' => 'Juvenil legacy',
                'slug' => 'juvenil-legacy',
                'level' => 1,
                'gender' => CategoryGender::MALE->value,
                'status' => 'active',
            ]);

            $this->assertFalse(Schema::hasColumn('categories', 'age_group'));

            $migration->up();

            $this->assertTrue(Schema::hasColumn('categories', 'age_group'));
            $this->assertDatabaseHas('categories', [
                'id' => $legacy->id,
                'name' => 'Juvenil legacy',
                'age_group' => null,
            ]);
        } finally {
            if (! Schema::hasColumn('categories', 'age_group')) {
                $migration->up();
            }
        }
    }
}
