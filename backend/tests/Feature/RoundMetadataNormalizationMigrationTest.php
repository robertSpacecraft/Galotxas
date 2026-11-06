<?php

namespace Tests\Feature;

use App\Models\Category;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\TestCase;

class RoundMetadataNormalizationMigrationTest extends TestCase
{
    use DatabaseTruncation;

    private Category $category;

    protected function setUp(): void
    {
        parent::setUp();

        $this->category = Category::factory()->create();
    }

    protected function tearDown(): void
    {
        try {
            $this->truncateDatabaseTables();
        } finally {
            parent::tearDown();
        }
    }

    public function test_normalizes_only_exact_legacy_across_categories_and_preserves_every_other_attribute(): void
    {
        $first = $this->insertRound('league', null, null, ['name' => 'Final', 'order' => 200]);
        $second = $this->insertRound('league', null, null, [
            'category_id' => Category::factory()->create()->id,
            'name' => 'Sin indicios de jornada',
            'order' => 42,
        ]);
        $this->insertCanonicalRounds();
        $before = $this->snapshot();
        Log::spy();

        $this->migration()->up();

        $expected = array_map(fn (array $row): array => in_array($row['id'], [$first, $second], true)
            ? array_replace($row, ['phase' => 'league', 'stage' => 'matchday'])
            : $row, $before);
        $this->assertSame($expected, $this->snapshot());
        Log::shouldHaveReceived('info')->once()->with(
            'Normalización de metadatos de rondas: legacy detectadas en preflight.',
            ['legacy_count' => 2],
        );
        $this->assertSame(0, DB::transactionLevel());
    }

    public function test_canonical_dataset_without_legacy_is_unchanged_and_data_idempotent(): void
    {
        $this->insertCanonicalRounds();
        $before = $this->snapshot();

        $this->migration()->up();
        $this->assertSame($before, $this->snapshot());

        $this->migration()->up();
        $this->assertSame($before, $this->snapshot());
    }

    public function test_empty_dataset_is_valid(): void
    {
        $this->migration()->up();

        $this->assertSame([], $this->snapshot());
    }

    #[DataProvider('ambiguousMetadata')]
    public function test_preflight_rejects_ambiguous_rows_before_updating_even_normalizable_legacy(
        string $type,
        ?string $phase,
        ?string $stage,
    ): void {
        $this->insertRound('league', null, null);
        $this->insertCanonicalRounds();
        $ambiguousId = $this->insertRound($type, $phase, $stage);
        $before = $this->snapshot();
        DB::enableQueryLog();
        DB::flushQueryLog();

        try {
            $this->migration()->up();
            $this->fail('El preflight debía rechazar la clasificación ambigua.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('Preflight', $exception->getMessage());
            $this->assertStringContainsString('existen 1 filas ambiguas o no soportadas', $exception->getMessage());
            $this->assertStringContainsString('(ids: '.$ambiguousId.')', $exception->getMessage());
            $this->assertStringContainsString('no las repara ni infiere', $exception->getMessage());
            $writes = array_filter(DB::getQueryLog(), fn (array $query): bool => preg_match('/^\s*(update|insert|delete)\b/i', $query['query']) === 1);
            $this->assertSame([], $writes);
        } finally {
            DB::disableQueryLog();
            DB::flushQueryLog();
        }

        $this->assertSame($before, $this->snapshot());
        $this->assertSame(0, DB::transactionLevel());
    }

    public static function ambiguousMetadata(): array
    {
        return [
            'league missing stage' => ['league', 'league', null],
            'league missing phase' => ['league', null, 'matchday'],
            'cup missing stage' => ['cup', 'cup', null],
            'cup with league phase' => ['cup', 'league', 'final'],
            'unknown type' => ['friendly', null, null],
            'league with cup stage' => ['league', null, 'final'],
            'league with cup phase' => ['league', 'cup', 'matchday'],
            'cup without metadata' => ['cup', null, null],
            'cup missing phase' => ['cup', null, 'final'],
            'unknown cup stage' => ['cup', 'cup', 'quarterfinal'],
            'league with semifinal' => ['league', 'league', 'semifinal'],
            'empty phase' => ['league', '', 'matchday'],
            'empty stage' => ['league', 'league', ''],
            'uppercase type' => ['LEAGUE', null, null],
            'uppercase phase' => ['league', 'LEAGUE', 'matchday'],
            'uppercase stage' => ['cup', 'cup', 'FINAL'],
            'type with trailing space' => ['league ', null, null],
            'phase with trailing space' => ['league', 'league ', 'matchday'],
            'stage with trailing space' => ['league', 'league', 'matchday '],
        ];
    }

    public function test_preflight_reports_total_count_with_a_bounded_ordered_sample_of_ids(): void
    {
        $this->insertRound('league', null, null);
        $ids = [];
        for ($i = 0; $i < 13; $i++) {
            $ids[] = $this->insertRound('friendly', null, null);
        }
        $before = $this->snapshot();

        try {
            $this->migration()->up();
            $this->fail('El preflight debía rechazar todas las filas ambiguas.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('existen 13 filas ambiguas o no soportadas', $exception->getMessage());
            preg_match('/\(ids: ([\d, ]+)\)/', $exception->getMessage(), $sample);
            $this->assertSame(implode(', ', array_slice($ids, 0, 10)), $sample[1] ?? null);
        }

        $this->assertSame($before, $this->snapshot());
    }

    public function test_count_mismatch_rolls_back_all_changes(): void
    {
        $first = $this->insertRound('league', null, null);
        $this->insertRound('league', null, null);
        $before = $this->snapshot();
        $injected = false;
        DB::connection()->beforeExecuting(function (string $sql) use ($first, &$injected): void {
            if (! $injected && str_starts_with($sql, 'update `rounds`')) {
                $injected = true;
                DB::table('rounds')->where('id', $first)->update(['phase' => 'league', 'stage' => 'matchday']);
            }
        });

        try {
            $this->migration()->up();
            $this->fail('El conteo actualizado debía diferir del preflight.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('se detectaron 2 legacy y se actualizaron 1 filas', $exception->getMessage());
        }

        $this->assertTrue($injected);
        $this->assertSame($before, $this->snapshot());
        $this->assertSame(0, DB::transactionLevel());
    }

    #[DataProvider('postflightFailures')]
    public function test_postflight_failure_rolls_back_the_backfill_and_the_injected_row(
        string $type,
        ?string $phase,
        ?string $stage,
        string $message,
    ): void {
        $this->insertRound('league', null, null);
        $this->insertCanonicalRounds();
        $before = $this->snapshot();
        $injected = false;
        DB::listen(function (QueryExecuted $query) use ($type, $phase, $stage, &$injected): void {
            if (! $injected && str_starts_with($query->sql, 'update `rounds`')) {
                $injected = true;
                $this->assertSame(1, DB::transactionLevel());
                $this->insertRound($type, $phase, $stage);
            }
        });

        try {
            $this->migration()->up();
            $this->fail('El postflight debía abortar y revertir la transacción.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('Postflight', $exception->getMessage());
            $this->assertStringContainsString($message, $exception->getMessage());
        }

        $this->assertTrue($injected);
        $this->assertSame($before, $this->snapshot());
        $this->assertSame(0, DB::transactionLevel());
    }

    public static function postflightFailures(): array
    {
        return [
            'remaining legacy' => ['league', null, null, 'quedan 1 filas league/NULL/NULL'],
            'new ambiguous row' => ['cup', null, 'final', 'existen 1 filas ambiguas o no soportadas'],
        ];
    }

    public function test_down_rejects_rollback_without_changing_data(): void
    {
        $this->insertRound('league', null, null);
        $this->insertCanonicalRounds();
        $before = $this->snapshot();

        try {
            $this->migration()->down();
            $this->fail('down() debía rechazar el retroceso.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('forward-only', $exception->getMessage());
        }

        $this->assertSame($before, $this->snapshot());
    }

    private function insertCanonicalRounds(): void
    {
        $this->insertRound('league', 'league', 'matchday');
        foreach (['semifinal', 'final', 'third_place'] as $stage) {
            $this->insertRound('cup', 'cup', $stage);
        }
    }

    private function insertRound(string $type, ?string $phase, ?string $stage, array $attributes = []): int
    {
        return DB::table('rounds')->insertGetId(array_replace([
            'category_id' => $this->category->id,
            'name' => 'Nombre sin valor clasificatorio',
            'order' => 7,
            'type' => $type,
            'phase' => $phase,
            'stage' => $stage,
            'created_at' => '2026-01-02 03:04:05',
            'updated_at' => '2026-02-03 04:05:06',
        ], $attributes));
    }

    private function snapshot(): array
    {
        return DB::table('rounds')->orderBy('id')->get()->map(fn ($row): array => (array) $row)->all();
    }

    private function migration(): Migration
    {
        return require database_path('migrations/2026_11_05_000000_normalize_league_round_metadata.php');
    }
}
