<?php

namespace Tests\Feature;

use App\Models\Venue;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class VenueCourtNumberMigrationTest extends TestCase
{
    use DatabaseTruncation;

    public function test_schema_has_nullable_unique_positive_court_number(): void
    {
        $this->assertTrue(Schema::hasColumn('venues', 'court_number'));

        $column = collect(DB::select(
            'SELECT DATA_TYPE, COLUMN_TYPE, IS_NULLABLE FROM information_schema.COLUMNS '
            .'WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?',
            ['venues', 'court_number']
        ))->sole();
        $this->assertSame('int', $column->DATA_TYPE);
        $this->assertStringContainsString('unsigned', $column->COLUMN_TYPE);
        $this->assertSame('YES', $column->IS_NULLABLE);

        $index = collect(DB::select(
            'SHOW INDEX FROM `venues` WHERE `Key_name` = ?',
            ['venues_court_number_unique']
        ))->sole();
        $this->assertSame('court_number', $index->Column_name);
        $this->assertSame(0, (int) $index->Non_unique);

        $check = collect(DB::select(
            'SELECT CHECK_CLAUSE FROM information_schema.CHECK_CONSTRAINTS '
            .'WHERE CONSTRAINT_SCHEMA = DATABASE() AND TABLE_NAME = ? AND CONSTRAINT_NAME = ?',
            ['venues', 'venues_court_number_positive']
        ))->sole();
        $this->assertStringContainsString('court_number', $check->CHECK_CLAUSE);
        $this->assertStringContainsString('> 0', $check->CHECK_CLAUSE);

    }

    public function test_database_rejects_zero_court_number(): void
    {
        try {
            DB::table('venues')->insert([
                'court_number' => 0,
                'name' => 'Pista inválida',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $this->fail('La restricción de base de datos debía rechazar el número cero.');
        } catch (QueryException $exception) {
            $this->assertStringContainsString('venues_court_number_positive', $exception->getMessage());
        }

        $this->assertDatabaseMissing('venues', ['name' => 'Pista inválida']);
    }

    public function test_migration_preserves_existing_venue_without_inferring_court_number(): void
    {
        $migration = require database_path(
            'migrations/2026_09_27_000001_add_court_number_to_venues_table.php'
        );
        $migration->down();

        try {
            $legacy = Venue::query()->create(['name' => 'Pista 4 legacy']);

            $this->assertFalse(Schema::hasColumn('venues', 'court_number'));

            $migration->up();

            $this->assertTrue(Schema::hasColumn('venues', 'court_number'));
            $this->assertDatabaseHas('venues', [
                'id' => $legacy->id,
                'name' => 'Pista 4 legacy',
                'court_number' => null,
            ]);
        } finally {
            if (! Schema::hasColumn('venues', 'court_number')) {
                $migration->up();
            }
        }
    }
}
