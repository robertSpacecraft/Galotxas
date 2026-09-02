<?php

namespace Tests\Feature;

use App\Enums\SeasonStatus;
use App\Models\Season;
use App\Services\SeasonService;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use PDOException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class SeasonActiveLifecycleTest extends TestCase
{
    use RefreshDatabase;

    public function test_factory_and_database_default_create_planned_seasons_and_allow_zero_active(): void
    {
        $factorySeason = Season::factory()->create();
        $databaseDefaultSeason = Season::query()->create([
            'name' => 'Temporada con default de base de datos',
        ])->refresh();

        $this->assertSame(SeasonStatus::PLANNED, $factorySeason->status);
        $this->assertSame(SeasonStatus::PLANNED, $databaseDefaultSeason->status);
        $this->assertSame(0, Season::query()->where('status', SeasonStatus::ACTIVE->value)->count());
        $this->assertArrayNotHasKey('active_slot', $databaseDefaultSeason->toArray());
    }

    public function test_service_allows_one_active_season_and_rejects_another_with_a_domain_error(): void
    {
        $service = app(SeasonService::class);
        $first = $service->create($this->attributes('Primera activa', SeasonStatus::ACTIVE));

        $this->assertSame(SeasonStatus::ACTIVE, $first->status);

        try {
            $service->create($this->attributes('Segunda activa', SeasonStatus::ACTIVE));
            $this->fail('La segunda temporada activa debería haberse rechazado.');
        } catch (ValidationException $exception) {
            $this->assertSame(
                SeasonService::ACTIVE_CONFLICT_ERROR,
                $exception->errors()['status'][0]
            );
        }

        $this->assertSame(1, Season::query()->where('status', SeasonStatus::ACTIVE->value)->count());
        $this->assertDatabaseMissing('seasons', ['name' => 'Segunda activa']);
    }

    public function test_service_allows_a_planned_season_to_become_active_and_editing_that_same_active_season(): void
    {
        $service = app(SeasonService::class);
        $season = $service->create($this->attributes('Planificada', SeasonStatus::PLANNED));

        $season = $service->update(
            $season,
            $this->attributes('Activa', SeasonStatus::ACTIVE)
        );
        $season = $service->update(
            $season,
            $this->attributes('Activa editada', SeasonStatus::ACTIVE)
        );

        $this->assertSame(SeasonStatus::ACTIVE, $season->status);
        $this->assertSame('Activa editada', $season->name);
        $this->assertSame(1, Season::query()->where('status', SeasonStatus::ACTIVE->value)->count());
    }

    public function test_service_rejects_planned_to_active_when_another_active_season_exists(): void
    {
        Season::factory()->active()->create();
        $planned = Season::factory()->create();

        try {
            app(SeasonService::class)->update(
                $planned,
                $this->attributes($planned->name, SeasonStatus::ACTIVE)
            );
            $this->fail('La transición a una segunda temporada activa debería haberse rechazado.');
        } catch (ValidationException $exception) {
            $this->assertSame(
                SeasonService::ACTIVE_CONFLICT_ERROR,
                $exception->errors()['status'][0]
            );
        }

        $this->assertSame(SeasonStatus::PLANNED, $planned->fresh()->status);
    }

    #[DataProvider('releasingStatusProvider')]
    public function test_leaving_active_releases_the_slot_for_another_season(
        SeasonStatus $releasingStatus
    ): void {
        $service = app(SeasonService::class);
        $active = Season::factory()->active()->create();
        $next = Season::factory()->create();

        $service->update(
            $active,
            $this->attributes($active->name, $releasingStatus)
        );
        $activated = $service->update(
            $next,
            $this->attributes($next->name, SeasonStatus::ACTIVE)
        );

        $this->assertSame($releasingStatus, $active->fresh()->status);
        $this->assertSame(SeasonStatus::ACTIVE, $activated->status);
        $this->assertSame(1, Season::query()->where('status', SeasonStatus::ACTIVE->value)->count());
    }

    /**
     * @return array<string, array{SeasonStatus}>
     */
    public static function releasingStatusProvider(): array
    {
        return [
            'finished' => [SeasonStatus::FINISHED],
            'cancelled' => [SeasonStatus::CANCELLED],
            'planned' => [SeasonStatus::PLANNED],
        ];
    }

    public function test_database_constraint_rejects_a_second_active_season_without_application_validation(): void
    {
        Season::factory()->active()->create();

        $this->expectException(QueryException::class);

        Season::factory()->active()->create();
    }

    public function test_service_translates_the_unique_constraint_race_without_exposing_sql(): void
    {
        $previous = new PDOException(
            "Duplicate entry '1' for key 'seasons.seasons_one_active_unique'"
        );
        $previous->errorInfo = [
            '23000',
            1062,
            "Duplicate entry '1' for key 'seasons.seasons_one_active_unique'",
        ];
        $queryException = new QueryException(
            'mariadb',
            'insert into `seasons` (`active_slot`) values (?)',
            [1],
            $previous
        );
        $season = new class extends Season
        {
            protected $table = 'seasons';

            public static ?QueryException $saveFailure = null;

            public function save(array $options = []): bool
            {
                throw self::$saveFailure;
            }
        };
        $season::$saveFailure = $queryException;

        try {
            app(SeasonService::class)->update(
                $season,
                $this->attributes('Carrera concurrente', SeasonStatus::ACTIVE)
            );
            $this->fail('La violación de la ranura activa debería haberse traducido.');
        } catch (ValidationException $exception) {
            $this->assertSame(
                SeasonService::ACTIVE_CONFLICT_ERROR,
                $exception->errors()['status'][0]
            );
            $this->assertStringNotContainsString('SQL', $exception->getMessage());
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function attributes(string $name, SeasonStatus $status): array
    {
        return [
            'name' => $name,
            'status' => $status->value,
            'is_public' => false,
            'start_date' => null,
            'end_date' => null,
        ];
    }
}
