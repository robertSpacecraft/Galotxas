<?php

namespace Tests\Feature;

use Illuminate\Database\Migrations\Migration;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use Tests\TestCase;

class SeasonActiveMigrationTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Migration DDL cannot run inside the transaction used by RefreshDatabase.
     * Every test below restores the latest migration in its finally block.
     *
     * @var array<int, string>
     */
    protected $connectionsToTransact = [];

    public function test_schema_uses_planned_default_and_a_stored_generated_unique_slot(): void
    {
        $status = collect(DB::select('SHOW FULL COLUMNS FROM `seasons`'))
            ->firstWhere('Field', 'status');
        $activeSlot = collect(DB::select('SHOW FULL COLUMNS FROM `seasons`'))
            ->firstWhere('Field', 'active_slot');
        $uniqueIndex = DB::select(
            "SHOW INDEX FROM `seasons` WHERE `Key_name` = 'seasons_one_active_unique'"
        );

        $this->assertSame('planned', $status->Default);
        $this->assertSame('STORED GENERATED', strtoupper($activeSlot->Extra));
        $this->assertCount(1, $uniqueIndex);
        $this->assertSame(0, $uniqueIndex[0]->Non_unique);
    }

    public function test_migration_aborts_without_rewriting_an_incompatible_status(): void
    {
        $migration = $this->migration();
        $migration->down();
        DB::table('seasons')->insert([
            ['name' => 'Temporada legada incompatible', 'status' => 'pending'],
            ['name' => 'Temporada con mayúsculas', 'status' => 'ACTIVE'],
        ]);

        try {
            $migration->up();
            $this->fail('La migración debería haber rechazado el estado incompatible.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('ACTIVE, pending', $exception->getMessage());
            $this->assertDatabaseHas('seasons', [
                'name' => 'Temporada legada incompatible',
                'status' => 'pending',
            ]);
            $this->assertFalse(Schema::hasColumn('seasons', 'active_slot'));
        } finally {
            DB::table('seasons')->delete();
            $migration->up();
        }
    }

    public function test_migration_aborts_without_choosing_between_multiple_active_seasons(): void
    {
        $migration = $this->migration();
        $migration->down();
        DB::table('seasons')->insert([
            ['name' => 'Activa A', 'status' => 'active'],
            ['name' => 'Activa B', 'status' => 'active'],
        ]);

        try {
            $migration->up();
            $this->fail('La migración debería haber rechazado múltiples temporadas activas.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('existen 2 temporadas activas', $exception->getMessage());
            $this->assertSame(2, DB::table('seasons')->where('status', 'active')->count());
            $this->assertFalse(Schema::hasColumn('seasons', 'active_slot'));
        } finally {
            DB::table('seasons')->delete();
            $migration->up();
        }
    }

    private function migration(): Migration
    {
        return require database_path(
            'migrations/2026_09_02_000000_enforce_single_active_season.php'
        );
    }
}
