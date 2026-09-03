<?php

namespace Tests\Feature;

use App\Models\Category;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\QueryException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use Tests\TestCase;

class CategoryOfficialResultMigrationTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Migration DDL cannot run inside the transaction used by RefreshDatabase.
     * Tests that change the schema restore the latest migration in finally.
     *
     * @var array<int, string>
     */
    protected $connectionsToTransact = [];

    /** @var list<string> */
    private const TABLES = [
        'category_official_results',
        'category_official_league_rows',
        'category_official_cup_winners',
        'category_official_result_match_snapshots',
    ];

    public function test_schema_contains_the_snapshot_tables_generated_slot_indexes_and_foreign_keys(): void
    {
        foreach (self::TABLES as $table) {
            $this->assertTrue(Schema::hasTable($table));
            $this->assertSame(0, DB::table($table)->count());
        }

        $this->assertTrue(Schema::hasColumns('category_official_results', [
            'category_id',
            'competition_part',
            'version',
            'status',
            'officialized_at',
            'officialized_by_user_id',
            'officialized_by_name_snapshot',
            'reopened_at',
            'reopened_by_user_id',
            'reopened_by_name_snapshot',
            'reopen_reason',
            'source_digest',
            'current_slot',
        ]));
        $this->assertTrue(Schema::hasColumns('category_official_league_rows', [
            'official_result_id',
            'position',
            'source_entry_id',
            'source_player_id',
            'source_team_id',
            'entry_type',
            'identity_projection',
            'display_name_snapshot',
            'public_display_name',
            'public_anonymized_at',
            'played',
            'wins',
            'losses',
            'points',
            'games_for',
            'games_against',
            'games_diff',
        ]));
        $this->assertTrue(Schema::hasColumns('category_official_cup_winners', [
            'official_result_id',
            'source_entry_id',
            'source_player_id',
            'source_team_id',
            'entry_type',
            'source_final_match_id',
            'identity_projection',
            'display_name_snapshot',
            'public_display_name',
            'public_anonymized_at',
        ]));
        $this->assertTrue(Schema::hasColumns(
            'category_official_result_match_snapshots',
            [
                'official_result_id',
                'source_game_match_id',
                'source_round_id',
                'stage',
                'home_entry_id',
                'away_entry_id',
                'home_score',
                'away_score',
                'winner_entry_id',
            ]
        ));

        $columns = collect(DB::select(<<<'SQL'
            SELECT TABLE_NAME, COLUMN_NAME, DATA_TYPE, COLUMN_TYPE, IS_NULLABLE
            FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME IN (
                'category_official_results',
                'category_official_league_rows',
                'category_official_cup_winners',
                'category_official_result_match_snapshots'
              )
            SQL))->keyBy(fn ($column): string => $column->TABLE_NAME.'.'.$column->COLUMN_NAME);

        foreach ([
            'category_official_results.category_id',
            'category_official_league_rows.official_result_id',
            'category_official_league_rows.source_entry_id',
            'category_official_league_rows.source_player_id',
            'category_official_league_rows.source_team_id',
            'category_official_cup_winners.official_result_id',
            'category_official_cup_winners.source_final_match_id',
            'category_official_result_match_snapshots.official_result_id',
            'category_official_result_match_snapshots.source_game_match_id',
            'category_official_result_match_snapshots.winner_entry_id',
        ] as $column) {
            $this->assertSame('bigint', $columns->get($column)->DATA_TYPE);
            $this->assertStringContainsString('unsigned', $columns->get($column)->COLUMN_TYPE);
        }

        foreach ([
            'category_official_results.version',
            'category_official_league_rows.position',
            'category_official_league_rows.played',
            'category_official_league_rows.wins',
            'category_official_league_rows.losses',
            'category_official_league_rows.points',
            'category_official_league_rows.games_for',
            'category_official_league_rows.games_against',
            'category_official_result_match_snapshots.home_score',
            'category_official_result_match_snapshots.away_score',
        ] as $column) {
            $this->assertSame('int', $columns->get($column)->DATA_TYPE);
            $this->assertStringContainsString('unsigned', $columns->get($column)->COLUMN_TYPE);
        }

        $this->assertSame('int', $columns->get(
            'category_official_league_rows.games_diff'
        )->DATA_TYPE);
        $this->assertStringNotContainsString('unsigned', $columns->get(
            'category_official_league_rows.games_diff'
        )->COLUMN_TYPE);
        $this->assertSame('enum', $columns->get(
            'category_official_results.competition_part'
        )->DATA_TYPE);
        $this->assertSame('enum', $columns->get(
            'category_official_results.status'
        )->DATA_TYPE);
        $this->assertSame('char', $columns->get(
            'category_official_results.source_digest'
        )->DATA_TYPE);
        $this->assertSame('NO', $columns->get(
            'category_official_results.officialized_by_name_snapshot'
        )->IS_NULLABLE);
        $this->assertSame('YES', $columns->get(
            'category_official_results.reopened_by_name_snapshot'
        )->IS_NULLABLE);

        $currentSlot = collect(DB::select(
            'SHOW FULL COLUMNS FROM `category_official_results`'
        ))->firstWhere('Field', 'current_slot');
        $indexes = collect(DB::select(
            'SHOW INDEX FROM `category_official_results`'
        ))->groupBy('Key_name');

        $this->assertSame('STORED GENERATED', strtoupper($currentSlot->Extra));
        $this->assertTrue($indexes->has('category_official_results_version_unique'));
        $this->assertTrue($indexes->has('category_official_results_current_unique'));
        $this->assertSame(
            0,
            (int) $indexes->get('category_official_results_current_unique')->first()->Non_unique
        );

        $foreignKeys = collect(DB::select(<<<'SQL'
            SELECT
                rc.TABLE_NAME AS TABLE_NAME,
                kcu.COLUMN_NAME AS COLUMN_NAME,
                kcu.REFERENCED_TABLE_NAME AS REFERENCED_TABLE_NAME,
                rc.DELETE_RULE AS DELETE_RULE
            FROM information_schema.REFERENTIAL_CONSTRAINTS rc
            JOIN information_schema.KEY_COLUMN_USAGE kcu
              ON kcu.CONSTRAINT_SCHEMA = rc.CONSTRAINT_SCHEMA
             AND kcu.CONSTRAINT_NAME = rc.CONSTRAINT_NAME
             AND kcu.TABLE_NAME = rc.TABLE_NAME
            WHERE rc.CONSTRAINT_SCHEMA = DATABASE()
              AND rc.TABLE_NAME IN (
                'category_official_results',
                'category_official_league_rows',
                'category_official_cup_winners',
                'category_official_result_match_snapshots'
              )
            SQL))->keyBy(fn ($row): string => $row->TABLE_NAME.'.'.$row->COLUMN_NAME);

        $this->assertCount(6, $foreignKeys);
        $this->assertSame(
            'RESTRICT',
            $foreignKeys->get('category_official_results.category_id')->DELETE_RULE
        );
        $this->assertSame(
            'SET NULL',
            $foreignKeys->get('category_official_results.officialized_by_user_id')->DELETE_RULE
        );
        $this->assertSame(
            'SET NULL',
            $foreignKeys->get('category_official_results.reopened_by_user_id')->DELETE_RULE
        );
        $this->assertSame(
            'CASCADE',
            $foreignKeys->get(
                'category_official_league_rows.official_result_id'
            )->DELETE_RULE
        );
        $this->assertSame(
            'CASCADE',
            $foreignKeys->get(
                'category_official_cup_winners.official_result_id'
            )->DELETE_RULE
        );
        $this->assertSame(
            'CASCADE',
            $foreignKeys->get(
                'category_official_result_match_snapshots.official_result_id'
            )->DELETE_RULE
        );

        $this->assertFalse(Schema::hasColumn(
            'category_official_league_rows',
            'email'
        ));
        $this->assertFalse(Schema::hasColumn(
            'category_official_cup_winners',
            'profile_photo_path'
        ));
    }

    public function test_migration_creates_empty_tables_without_touching_existing_categories(): void
    {
        $category = Category::factory()->create();
        $championship = $category->championship;
        $season = $championship->season;
        $migration = $this->migration();

        try {
            $migration->down();

            foreach (self::TABLES as $table) {
                $this->assertFalse(Schema::hasTable($table));
            }

            $migration->up();

            $this->assertDatabaseHas('categories', ['id' => $category->id]);
            foreach (self::TABLES as $table) {
                $this->assertSame(0, DB::table($table)->count());
            }
        } finally {
            if (! Schema::hasTable('category_official_results')) {
                $migration->up();
            }

            $category->delete();
            $championship->delete();
            $season->delete();
        }
    }

    public function test_migration_preserves_an_existing_table_and_its_data_on_collision(): void
    {
        $migration = $this->migration();
        $migration->down();

        Schema::create('category_official_league_rows', function (Blueprint $table) {
            $table->id();
            $table->string('existing_marker');
        });
        DB::table('category_official_league_rows')->insert([
            'id' => 99,
            'existing_marker' => 'preserve-me',
        ]);

        try {
            $migration->up();
            $this->fail('La migración debería rechazar la colisión de tablas.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString(
                'category_official_league_rows',
                $exception->getMessage()
            );
            $this->assertTrue(Schema::hasTable('category_official_league_rows'));
            $this->assertTrue(Schema::hasColumn(
                'category_official_league_rows',
                'existing_marker'
            ));
            $this->assertDatabaseHas('category_official_league_rows', [
                'id' => 99,
                'existing_marker' => 'preserve-me',
            ]);
            $this->assertFalse(Schema::hasTable('category_official_results'));
            $this->assertFalse(Schema::hasTable('category_official_cup_winners'));
        } finally {
            Schema::dropIfExists('category_official_league_rows');
            $migration->up();
        }
    }

    public function test_migration_cleans_only_tables_created_before_a_partial_failure(): void
    {
        $migration = $this->migration();
        $migration->down();

        Schema::create('official_result_migration_failure_fixture', function (Blueprint $table) {
            $table->id();
            $table->foreignId('category_id');
            $table->foreign(
                'category_id',
                'official_league_rows_result_foreign'
            )->references('id')->on('categories')->restrictOnDelete();
        });

        try {
            $migration->up();
            $this->fail('La migración debería fallar al colisionar el nombre de la FK.');
        } catch (QueryException) {
            $this->assertTrue(Schema::hasTable(
                'official_result_migration_failure_fixture'
            ));

            foreach (self::TABLES as $table) {
                $this->assertFalse(Schema::hasTable($table));
            }
        } finally {
            Schema::dropIfExists('official_result_migration_failure_fixture');
            $migration->up();
        }
    }

    private function migration(): Migration
    {
        return require database_path(
            'migrations/2026_09_03_000000_create_category_official_results_tables.php'
        );
    }
}
