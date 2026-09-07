<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Championship;
use App\Models\Season;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class CompetitionImageMigrationTest extends TestCase
{
    use RefreshDatabase;

    // MariaDB DDL commits implicitly; restore the schema and fixture explicitly.
    protected $connectionsToTransact = [];

    public function test_additive_migration_and_down_preserve_existing_rows_and_legacy_columns(): void
    {
        $this->assertSame('galotxas_testing', DB::connection()->getDatabaseName());
        $this->assertSame('test-db', DB::connection()->getConfig('host'));
        $migration = require database_path('migrations/2026_09_07_000001_add_image_path_to_seasons_table.php');
        $season = null;

        try {
            $migration->down();
            $this->assertFalse(Schema::hasColumn('seasons', 'image_path'));
            $this->assertTrue(Schema::hasColumn('championships', 'image_path'));
            $this->assertTrue(Schema::hasColumn('categories', 'image_path'));
            $season = Season::factory()->create();
            $championship = Championship::factory()->create([
                'season_id' => $season->id, 'image_path' => '/legacy/championship.jpg',
            ]);
            $category = Category::factory()->create([
                'championship_id' => $championship->id, 'image_path' => 'https://legacy.invalid/category.png',
            ]);
            $before = $season->fresh()->getAttributes();
            $migration->up();

            $this->assertTrue(Schema::hasColumn('seasons', 'image_path'));
            $this->assertSame([...$before, 'image_path' => null], $season->fresh()->getAttributes());
            $this->assertSame('/legacy/championship.jpg', $championship->fresh()->image_path);
            $this->assertSame('https://legacy.invalid/category.png', $category->fresh()->image_path);
            $this->assertContains('image_path', $season->getFillable());
            $season->update(['image_path' => 'banners/11111111-1111-4111-8111-111111111111.jpg']);
            $migration->down();
            $this->assertSame($before, $season->fresh()->getAttributes());
            $this->assertSame('/legacy/championship.jpg', $championship->fresh()->image_path);
            $this->assertSame('https://legacy.invalid/category.png', $category->fresh()->image_path);
        } finally {
            if (! Schema::hasColumn('seasons', 'image_path')) {
                $migration->up();
            }
            $season?->delete();
        }
    }
}
