<?php

namespace Tests\Feature;

use App\Models\GameMatch;
use App\Models\Venue;
use App\Services\VenueOccupancyService;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Mockery;
use RuntimeException;
use Tests\TestCase;

class GameMatchVenueOccupancyMigrationTest extends TestCase
{
    use DatabaseTruncation;

    private const MIGRATION = '2026_09_27_000000_enforce_game_match_venue_occupancy.php';

    private const HELPER_INDEX = 'tmp_game_matches_venue_id';

    protected function tearDown(): void
    {
        try {
            if (! $this->guaranteesPresent()) {
                DB::table('game_matches')->delete();
                $this->migration()->up();
            }

            $this->dropHelperIndex();
            $this->truncateTablesForAllConnections();
        } finally {
            parent::tearDown();
        }
    }

    public function test_schema_has_a_stored_guard_named_unique_index_and_valid_venue_foreign_key(): void
    {
        $guard = collect(DB::select(
            'SELECT COLUMN_NAME, EXTRA, GENERATION_EXPRESSION FROM information_schema.COLUMNS '
            .'WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?',
            ['game_matches', VenueOccupancyService::GENERATED_COLUMN]
        ))->first();
        $index = collect(DB::select(
            'SHOW INDEX FROM `game_matches` WHERE `Key_name` = ?',
            [VenueOccupancyService::UNIQUE_INDEX]
        ))->sortBy('Seq_in_index');

        $this->assertNotNull($guard);
        $this->assertSame('STORED GENERATED', strtoupper($guard->EXTRA));
        foreach (['scheduled', 'submitted', 'under_review', 'validated'] as $status) {
            $this->assertStringContainsString($status, $guard->GENERATION_EXPRESSION);
        }
        $this->assertSame(
            ['venue_id', 'scheduled_date', 'occupancy_guard'],
            $index->pluck('Column_name')->values()->all()
        );
        $this->assertSame([0], $index->pluck('Non_unique')->map(fn ($value): int => (int) $value)->unique()->values()->all());

        $venueForeign = collect(DB::select(
            'SELECT CONSTRAINT_NAME, DELETE_RULE FROM information_schema.REFERENTIAL_CONSTRAINTS '
            .'WHERE CONSTRAINT_SCHEMA = DATABASE() AND TABLE_NAME = ? AND CONSTRAINT_NAME = ?',
            ['game_matches', 'game_matches_venue_id_foreign']
        ))->sole();
        $this->assertSame('SET NULL', $venueForeign->DELETE_RULE);
        $this->assertNotEmpty(collect(DB::select('SHOW INDEX FROM `game_matches`'))
            ->where('Column_name', 'venue_id')
            ->where('Seq_in_index', 1));
    }

    public function test_constraint_rejects_a_second_occupying_row_but_allows_released_and_unscheduled_rows(): void
    {
        $base = GameMatch::factory()->create([
            'scheduled_date' => '2026-10-01 17:00:00',
            'status' => 'scheduled',
        ]);

        foreach (['postponed', 'cancelled'] as $status) {
            $this->duplicateOf($base, $status);
            $this->duplicateOf($base, $status);
        }

        GameMatch::factory()->count(2)->create([
            'venue_id' => null,
            'scheduled_date' => null,
            'status' => 'scheduled',
        ]);

        try {
            $this->duplicateOf($base, 'submitted');
            $this->fail('El índice debía rechazar el segundo partido ocupante.');
        } catch (QueryException $exception) {
            $this->assertSame(1062, $exception->errorInfo[1]);
            $this->assertStringContainsString(VenueOccupancyService::UNIQUE_INDEX, $exception->getMessage());
            $this->assertNotNull(app(VenueOccupancyService::class)->conflictFrom($exception));
        }
    }

    public function test_constraint_allows_same_venue_at_another_hour_and_another_venue_at_the_same_hour(): void
    {
        $base = GameMatch::factory()->create([
            'scheduled_date' => '2026-10-01 17:00:00',
            'status' => 'scheduled',
        ]);
        $otherVenue = Venue::factory()->create();

        GameMatch::factory()->create([
            'round_id' => $base->round_id,
            'venue_id' => $base->venue_id,
            'home_entry_id' => $base->home_entry_id,
            'away_entry_id' => $base->away_entry_id,
            'scheduled_date' => '2026-10-01 18:00:00',
            'status' => 'submitted',
        ]);
        GameMatch::factory()->create([
            'round_id' => $base->round_id,
            'venue_id' => $otherVenue->id,
            'home_entry_id' => $base->home_entry_id,
            'away_entry_id' => $base->away_entry_id,
            'scheduled_date' => '2026-10-01 17:00:00',
            'status' => 'under_review',
        ]);

        $this->assertSame(3, GameMatch::query()->count());
    }

    public function test_released_to_occupying_transition_is_rejected_and_occupying_to_released_frees_the_slot(): void
    {
        $occupying = GameMatch::factory()->create([
            'scheduled_date' => '2026-10-01 17:00:00',
            'status' => 'scheduled',
        ]);
        $released = $this->duplicateOf($occupying, 'cancelled');

        try {
            $released->update(['status' => 'validated']);
            $this->fail('La transición debía colisionar con el propietario actual.');
        } catch (QueryException $exception) {
            $this->assertSame(1062, $exception->errorInfo[1]);
        }

        $occupying->update(['status' => 'postponed']);
        $released->update(['status' => 'validated']);

        $this->assertSame('postponed', $occupying->fresh()->status->value);
        $this->assertSame('validated', $released->fresh()->status->value);
        $released->update(['status' => 'validated']);
    }

    public function test_migration_aborts_before_schema_change_on_occupying_duplicates_without_repair(): void
    {
        $this->dropGuarantees();
        $base = GameMatch::factory()->create([
            'scheduled_date' => '2026-10-01 17:00:00',
            'status' => 'scheduled',
        ]);
        $duplicate = $this->duplicateOf($base, 'validated');
        $before = DB::table('game_matches')->orderBy('id')->get();

        try {
            $this->migration()->up();
            $this->fail('La migración debía abortar ante ocupación duplicada.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('existen 1 grupos y 2 filas ocupantes', $exception->getMessage());
            $this->assertStringContainsString('venue='.$base->venue_id, $exception->getMessage());
            $this->assertStringContainsString('ids='.$base->id.','.$duplicate->id, $exception->getMessage());
            $this->assertStringContainsString('no repara, mueve, redondea ni elimina', $exception->getMessage());
        }

        $this->assertFalse($this->guaranteesPresent());
        $this->assertEquals($before, DB::table('game_matches')->orderBy('id')->get());
    }

    public function test_migration_aborts_before_schema_change_on_overlapping_occupying_intervals(): void
    {
        $this->dropGuarantees();
        $first = GameMatch::factory()->create([
            'scheduled_date' => '2026-10-01 17:30:00',
            'status' => 'scheduled',
        ]);
        $second = GameMatch::factory()->create([
            'round_id' => $first->round_id,
            'venue_id' => $first->venue_id,
            'home_entry_id' => $first->home_entry_id,
            'away_entry_id' => $first->away_entry_id,
            'scheduled_date' => '2026-10-01 18:00:00',
            'status' => 'validated',
        ]);
        $before = DB::table('game_matches')->orderBy('id')->get();

        try {
            $this->migration()->up();
            $this->fail('La migración debía abortar ante intervalos ocupantes solapados.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString(
                'existen 1 pares de partidos ocupantes con intervalos de una hora solapados',
                $exception->getMessage()
            );
            $this->assertStringContainsString('venue='.$first->venue_id, $exception->getMessage());
            $this->assertStringContainsString('ids='.$first->id.','.$second->id, $exception->getMessage());
            $this->assertStringContainsString('inicios=2026-10-01 17:30:00,2026-10-01 18:00:00', $exception->getMessage());
        }

        $this->assertFalse($this->guaranteesPresent());
        $this->assertEquals($before, DB::table('game_matches')->orderBy('id')->get());
    }

    public function test_migration_reports_non_canonical_occupying_start_without_repair_or_blocking_ddl(): void
    {
        $this->dropGuarantees();
        $match = GameMatch::factory()->create([
            'scheduled_date' => '2026-10-01 17:30:23',
            'status' => 'scheduled',
        ]);
        $before = DB::table('game_matches')->where('id', $match->id)->first();
        Log::spy();

        $this->migration()->up();

        $this->assertTrue($this->guaranteesPresent());
        $this->assertDatabaseHas('game_matches', [
            'id' => $match->id,
            'scheduled_date' => '2026-10-01 17:30:23',
        ]);
        $after = DB::table('game_matches')->where('id', $match->id)->first();
        $this->assertEquals(
            collect((array) $before)->all(),
            collect((array) $after)->except('occupancy_guard')->all()
        );
        Log::shouldHaveReceived('warning')
            ->once()
            ->with(
                'Preflight F1: inicios ocupantes legacy no canónicos preservados sin cambios.',
                Mockery::on(fn (array $context): bool => $context['count'] === 1
                    && $context['sample'][0]['match_id'] === $match->id
                    && $context['sample'][0]['venue_id'] === $match->venue_id
                    && $context['sample'][0]['scheduled_date'] === '2026-10-01 17:30:23')
            );
    }

    public function test_migration_allows_released_non_canonical_duplicates_and_incomplete_slots(): void
    {
        $this->dropGuarantees();
        $base = GameMatch::factory()->create([
            'scheduled_date' => '2026-10-01 17:30:23',
            'status' => 'cancelled',
        ]);
        $this->duplicateOf($base, 'postponed');
        GameMatch::factory()->create([
            'venue_id' => null,
            'scheduled_date' => '2026-10-02 18:00:00',
            'status' => 'scheduled',
        ]);
        GameMatch::factory()->create([
            'venue_id' => Venue::factory()->create()->id,
            'scheduled_date' => null,
            'status' => 'scheduled',
        ]);
        GameMatch::factory()->create([
            'venue_id' => null,
            'scheduled_date' => null,
            'status' => 'scheduled',
        ]);

        $nonOverlappingVenue = Venue::factory()->create();
        GameMatch::factory()->create([
            'venue_id' => $nonOverlappingVenue->id,
            'scheduled_date' => '2026-10-03 17:30:00',
            'status' => 'scheduled',
        ]);
        GameMatch::factory()->create([
            'venue_id' => $nonOverlappingVenue->id,
            'scheduled_date' => '2026-10-03 19:00:00',
            'status' => 'submitted',
        ]);

        $adjacentVenue = Venue::factory()->create();
        GameMatch::factory()->create([
            'venue_id' => $adjacentVenue->id,
            'scheduled_date' => '2026-10-04 17:00:00',
            'status' => 'under_review',
        ]);
        GameMatch::factory()->create([
            'venue_id' => $adjacentVenue->id,
            'scheduled_date' => '2026-10-04 18:00:00',
            'status' => 'validated',
        ]);

        $releasedOverlapVenue = Venue::factory()->create();
        GameMatch::factory()->create([
            'venue_id' => $releasedOverlapVenue->id,
            'scheduled_date' => '2026-10-05 17:30:00',
            'status' => 'cancelled',
        ]);
        GameMatch::factory()->create([
            'venue_id' => $releasedOverlapVenue->id,
            'scheduled_date' => '2026-10-05 18:00:00',
            'status' => 'postponed',
        ]);
        $before = DB::table('game_matches')->orderBy('id')->get();

        $this->migration()->up();

        $this->assertTrue($this->guaranteesPresent());
        $this->assertEquals(
            $before->map(fn ($row) => collect((array) $row)->except('occupancy_guard')->all())->all(),
            DB::table('game_matches')->orderBy('id')->get()
                ->map(fn ($row) => collect((array) $row)->except('occupancy_guard')->all())->all()
        );
    }

    public function test_down_is_forward_only_and_preserves_the_guarantee(): void
    {
        try {
            $this->migration()->down();
            $this->fail('down() debía rechazar el retroceso.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('forward-only', $exception->getMessage());
        }

        $this->assertTrue($this->guaranteesPresent());
    }

    private function duplicateOf(GameMatch $match, string $status): GameMatch
    {
        return GameMatch::factory()->create([
            'round_id' => $match->round_id,
            'venue_id' => $match->venue_id,
            'home_entry_id' => $match->home_entry_id,
            'away_entry_id' => $match->away_entry_id,
            'scheduled_date' => $match->scheduled_date,
            'status' => $status,
        ]);
    }

    private function dropGuarantees(): void
    {
        DB::statement(sprintf(
            'ALTER TABLE `game_matches` ADD INDEX `%s` (`venue_id`), '
            .'DROP INDEX `%s`, DROP COLUMN `%s`',
            self::HELPER_INDEX,
            VenueOccupancyService::UNIQUE_INDEX,
            VenueOccupancyService::GENERATED_COLUMN,
        ));
    }

    private function dropHelperIndex(): void
    {
        if ($this->indexPresent(self::HELPER_INDEX)) {
            DB::statement(sprintf(
                'ALTER TABLE `game_matches` DROP INDEX `%s`',
                self::HELPER_INDEX
            ));
        }
    }

    private function guaranteesPresent(): bool
    {
        return Schema::hasColumn('game_matches', VenueOccupancyService::GENERATED_COLUMN)
            && $this->indexPresent(VenueOccupancyService::UNIQUE_INDEX);
    }

    private function indexPresent(string $name): bool
    {
        return DB::select('SHOW INDEX FROM `game_matches` WHERE `Key_name` = ?', [$name]) !== [];
    }

    private function migration(): Migration
    {
        return require database_path('migrations/'.self::MIGRATION);
    }
}
