<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\CategoryEntry;
use App\Models\Player;
use App\Models\Team;
use App\Services\CategoryEntryService;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\TestCase;

class CategoryEntryIdentityMigrationTest extends TestCase
{
    use DatabaseTruncation;

    private const MIGRATION = '2026_09_24_000000_enforce_category_entry_identity_integrity.php';

    private const HELPER_INDEX = 'tmp_category_entries_category_id';

    /**
     * The migration is DDL and forward-only, so these tests run without a wrapping
     * transaction and restore the guarantees themselves.
     */
    protected function tearDown(): void
    {
        try {
            if (! $this->guaranteesPresent()) {
                DB::table('category_entries')->delete();
                $this->migration()->up();
            }

            $this->dropHelperIndex();
            $this->truncateTablesForAllConnections();
        } finally {
            parent::tearDown();
        }
    }

    public function test_schema_has_the_named_check_the_unique_indexes_and_unchanged_cascading_foreign_keys(): void
    {
        $check = collect(DB::select(
            'SELECT CONSTRAINT_NAME, CHECK_CLAUSE FROM information_schema.CHECK_CONSTRAINTS '
            .'WHERE CONSTRAINT_SCHEMA = DATABASE() AND TABLE_NAME = ?',
            ['category_entries']
        ))->firstWhere('CONSTRAINT_NAME', CategoryEntryService::IDENTITY_CHECK);

        $this->assertNotNull($check);
        foreach (['entry_type', 'player_id', 'team_id'] as $column) {
            $this->assertStringContainsString($column, $check->CHECK_CLAUSE);
        }

        foreach ([
            CategoryEntryService::PLAYER_UNIQUE_INDEX => ['category_id', 'player_id'],
            CategoryEntryService::TEAM_UNIQUE_INDEX => ['category_id', 'team_id'],
        ] as $name => $columns) {
            $index = collect(DB::select('SHOW INDEX FROM category_entries WHERE Key_name = ?', [$name]))
                ->sortBy('Seq_in_index');

            $this->assertSame($columns, $index->pluck('Column_name')->values()->all(), $name);
            $this->assertSame([0], $index->pluck('Non_unique')->map(fn ($value): int => (int) $value)->unique()->values()->all());
        }

        $rules = collect(DB::select(
            'SELECT CONSTRAINT_NAME, DELETE_RULE FROM information_schema.REFERENTIAL_CONSTRAINTS '
            .'WHERE CONSTRAINT_SCHEMA = DATABASE() AND TABLE_NAME = ?',
            ['category_entries']
        ))->pluck('DELETE_RULE', 'CONSTRAINT_NAME')->all();

        $this->assertSame([
            'category_entries_category_id_foreign' => 'CASCADE',
            'category_entries_player_id_foreign' => 'CASCADE',
            'category_entries_team_id_foreign' => 'CASCADE',
        ], $rules);
    }

    public function test_the_category_foreign_key_stays_indexed_and_enforced(): void
    {
        // InnoDB replaces the implicit category_id index with the composite unique one.
        $leading = collect(DB::select('SHOW INDEX FROM category_entries'))
            ->where('Column_name', 'category_id')
            ->where('Seq_in_index', 1);
        $this->assertGreaterThanOrEqual(1, $leading->count());

        try {
            DB::table('category_entries')->insert([
                'category_id' => 999999,
                'entry_type' => 'player',
                'player_id' => Player::factory()->create()->id,
                'team_id' => null,
                'status' => 'approved',
            ]);
            $this->fail('La clave foránea de categoría debía seguir aplicándose.');
        } catch (QueryException $exception) {
            $this->assertSame(1452, $exception->errorInfo[1]);
            $this->assertNull(app(CategoryEntryService::class)->integrityViolationFrom($exception));
        }
    }

    #[DataProvider('malformedShapes')]
    public function test_the_check_rejects_malformed_identities_with_a_translatable_error(string $shape): void
    {
        [$category, $player, $team] = $this->parents();

        try {
            DB::table('category_entries')->insert($this->row($shape, $category, $player, $team));
            $this->fail('El CHECK debía rechazar la fila.');
        } catch (QueryException $exception) {
            $this->assertSame(4025, $exception->errorInfo[1]);
            $this->assertNotNull(app(CategoryEntryService::class)->integrityViolationFrom($exception));
        }

        $this->assertSame(0, CategoryEntry::query()->count());
    }

    public function test_unique_indexes_reject_duplicates_but_allow_nulls_and_other_categories(): void
    {
        [$category, $player, $team] = $this->parents();
        $other = Category::factory()->create();
        $service = app(CategoryEntryService::class);

        $this->insert('valid_player', $category, $player, $team);
        $this->insert('valid_team', $category, $player, $team);
        // Repeated NULLs never collide; the same identity in another category is a different key.
        $this->insert('valid_player', $category, Player::factory()->create(), $team, 'pending');
        $this->insert('valid_team', $category, $player, Team::factory()->create(['category_id' => $category->id]));
        $this->insert('valid_player', $other, $player, $team);
        $this->assertSame(5, CategoryEntry::query()->count());

        foreach (['valid_player' => 'Este jugador ya participa en esta categoría.', 'valid_team' => 'Este equipo ya participa en esta categoría.'] as $shape => $message) {
            try {
                $this->insert($shape, $category, $player, $team, 'rejected');
                $this->fail('La unicidad debía rechazar el duplicado.');
            } catch (QueryException $exception) {
                $this->assertSame(1062, $exception->errorInfo[1]);
                $this->assertSame($message, $service->integrityViolationFrom($exception)?->getMessage());
            }
        }

        $this->assertSame(5, CategoryEntry::query()->count());
    }

    public function test_foreign_key_cascades_still_delete_entries_despite_the_check(): void
    {
        [$category, $player, $team] = $this->parents();
        $this->insert('valid_player', $category, $player, $team);
        $this->insert('valid_team', $category, $player, $team);

        $player->delete();
        $this->assertSame(1, CategoryEntry::query()->count());
        $team->delete();
        $this->assertSame(0, CategoryEntry::query()->count());

        $this->insert('valid_player', $category, Player::factory()->create(), $team);
        $category->delete();
        $this->assertSame(0, CategoryEntry::query()->count());
    }

    public function test_migration_applies_on_clean_data_without_touching_it(): void
    {
        [$category, $player, $team] = $this->parents();
        $this->dropGuarantees();
        $this->insert('valid_player', $category, $player, $team);
        $this->insert('valid_team', $category, $player, $team);
        $before = DB::table('category_entries')->orderBy('id')->get();

        $this->migration()->up();

        $this->assertTrue($this->guaranteesPresent());
        $this->assertEquals($before, DB::table('category_entries')->orderBy('id')->get());
    }

    public function test_migration_applies_on_an_empty_table(): void
    {
        $this->dropGuarantees();

        $this->migration()->up();

        $this->assertTrue($this->guaranteesPresent());
    }

    #[DataProvider('malformedShapes')]
    public function test_migration_aborts_before_any_schema_change_on_malformed_legacy_rows(string $shape, string $label): void
    {
        [$category, $player, $team] = $this->parents();
        $this->dropGuarantees();
        $this->insert($shape, $category, $player, $team);
        $before = DB::table('category_entries')->orderBy('id')->get();

        try {
            $this->migration()->up();
            $this->fail('La migración debía abortar.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('existen 1 '.$label, $exception->getMessage());
            $this->assertStringContainsString('ids: '.$before->first()->id, $exception->getMessage());
            $this->assertStringContainsString('no repara, fusiona ni elimina', $exception->getMessage());
        }

        $this->assertFalse($this->guaranteesPresent());
        $this->assertSame([], $this->presentGuarantees());
        $this->assertEquals($before, DB::table('category_entries')->orderBy('id')->get());
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function malformedShapes(): array
    {
        return [
            'both identities' => ['both', 'entradas con jugador y equipo a la vez'],
            'no identity' => ['none', 'entradas sin jugador ni equipo'],
            'player type with a team' => ['player_with_team', 'entradas de tipo player con equipo en lugar de jugador'],
            'team type with a player' => ['team_with_player', 'entradas de tipo team con jugador en lugar de equipo'],
        ];
    }

    public function test_migration_aborts_on_duplicate_legacy_player_rows_without_repairing_them(): void
    {
        [$category, $player, $team] = $this->parents();
        $this->dropGuarantees();
        $this->insert('valid_player', $category, $player, $team, 'approved');
        $this->insert('valid_player', $category, $player, $team, 'pending');
        $before = DB::table('category_entries')->orderBy('id')->get();

        try {
            $this->migration()->up();
            $this->fail('La migración debía abortar.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('existen 1 grupos de jugador repetido en una misma categoría', $exception->getMessage());
            $this->assertStringContainsString('primer id de cada grupo: '.$before->first()->id, $exception->getMessage());
            $this->assertStringNotContainsString('equipo repetido', $exception->getMessage());
        }

        $this->assertSame([], $this->presentGuarantees());
        $this->assertEquals($before, DB::table('category_entries')->orderBy('id')->get());
    }

    public function test_migration_aborts_on_duplicate_legacy_team_rows_without_repairing_them(): void
    {
        [$category, $player, $team] = $this->parents();
        $this->dropGuarantees();
        $this->insert('valid_team', $category, $player, $team, 'approved');
        $this->insert('valid_team', $category, $player, $team, 'rejected');
        $before = DB::table('category_entries')->orderBy('id')->get();

        try {
            $this->migration()->up();
            $this->fail('La migración debía abortar.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('existen 1 grupos de equipo repetido en una misma categoría', $exception->getMessage());
            $this->assertStringNotContainsString('jugador repetido', $exception->getMessage());
        }

        $this->assertSame([], $this->presentGuarantees());
        $this->assertEquals($before, DB::table('category_entries')->orderBy('id')->get());
    }

    public function test_migration_reports_every_violation_at_once(): void
    {
        [$category, $player, $team] = $this->parents();
        $this->dropGuarantees();
        $this->insert('both', $category, $player, $team);
        $this->insert('none', $category, $player, $team);
        $this->insert('valid_player', $category, $player, $team);
        $this->insert('valid_player', $category, $player, $team);

        try {
            $this->migration()->up();
            $this->fail('La migración debía abortar.');
        } catch (RuntimeException $exception) {
            foreach ([
                'existen 1 entradas con jugador y equipo a la vez',
                'existen 1 entradas sin jugador ni equipo',
                'existen 1 grupos de jugador repetido',
            ] as $fragment) {
                $this->assertStringContainsString($fragment, $exception->getMessage());
            }
        }

        $this->assertSame(4, CategoryEntry::query()->count());
        $this->assertSame([], $this->presentGuarantees());
    }

    public function test_down_is_forward_only_and_keeps_the_guarantees(): void
    {
        try {
            $this->migration()->down();
            $this->fail('down() debía rechazar el retroceso.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('forward-only', $exception->getMessage());
        }

        $this->assertTrue($this->guaranteesPresent());
    }

    /**
     * @return array{Category, Player, Team}
     */
    private function parents(): array
    {
        $category = Category::factory()->create();

        return [
            $category,
            Player::factory()->create(),
            Team::factory()->create(['category_id' => $category->id]),
        ];
    }

    private function insert(string $shape, Category $category, Player $player, Team $team, string $status = 'approved'): void
    {
        DB::table('category_entries')->insert([...$this->row($shape, $category, $player, $team), 'status' => $status]);
    }

    /**
     * @return array<string, mixed>
     */
    private function row(string $shape, Category $category, Player $player, Team $team): array
    {
        [$type, $playerId, $teamId] = match ($shape) {
            'valid_player' => ['player', $player->id, null],
            'valid_team' => ['team', null, $team->id],
            'both' => ['player', $player->id, $team->id],
            'none' => ['player', null, null],
            'player_with_team' => ['player', null, $team->id],
            'team_with_player' => ['team', $player->id, null],
        };

        return [
            'category_id' => $category->id,
            'entry_type' => $type,
            'player_id' => $playerId,
            'team_id' => $teamId,
            'status' => 'approved',
            'created_at' => now(),
            'updated_at' => now(),
        ];
    }

    /**
     * InnoDB serves the category FK with the composite unique index, so a helper
     * index must take over before the guarantees can be dropped.
     */
    private function dropGuarantees(): void
    {
        DB::statement(<<<'SQL'
            ALTER TABLE `category_entries`
                ADD INDEX `tmp_category_entries_category_id` (`category_id`),
                DROP CONSTRAINT `category_entries_identity_check`,
                DROP INDEX `category_entries_category_player_unique`,
                DROP INDEX `category_entries_category_team_unique`
            SQL);
    }

    private function dropHelperIndex(): void
    {
        $exists = DB::select(
            'SELECT 1 FROM information_schema.STATISTICS '
            .'WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND INDEX_NAME = ? LIMIT 1',
            ['category_entries', self::HELPER_INDEX]
        );

        if ($exists !== []) {
            DB::statement('ALTER TABLE `category_entries` DROP INDEX `'.self::HELPER_INDEX.'`');
        }
    }

    /**
     * @return list<string>
     */
    private function presentGuarantees(): array
    {
        $check = DB::select(
            'SELECT CONSTRAINT_NAME AS name FROM information_schema.CHECK_CONSTRAINTS '
            .'WHERE CONSTRAINT_SCHEMA = DATABASE() AND TABLE_NAME = ? AND CONSTRAINT_NAME = ?',
            ['category_entries', CategoryEntryService::IDENTITY_CHECK]
        );
        $indexes = DB::select(
            'SELECT DISTINCT INDEX_NAME AS name FROM information_schema.STATISTICS '
            .'WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND INDEX_NAME IN (?, ?)',
            ['category_entries', CategoryEntryService::PLAYER_UNIQUE_INDEX, CategoryEntryService::TEAM_UNIQUE_INDEX]
        );

        return collect([...$check, ...$indexes])->pluck('name')->sort()->values()->all();
    }

    private function guaranteesPresent(): bool
    {
        return count($this->presentGuarantees()) === 3;
    }

    private function migration(): Migration
    {
        return require database_path('migrations/'.self::MIGRATION);
    }
}
