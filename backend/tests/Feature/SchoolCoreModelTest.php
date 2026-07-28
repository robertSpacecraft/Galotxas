<?php

namespace Tests\Feature;

use App\Enums\SchoolDayOfWeek;
use App\Models\SchoolLevel;
use App\Models\SchoolLocation;
use App\Models\SchoolProgram;
use App\Models\SchoolSchedule;
use App\Services\SchoolProgramService;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class SchoolCoreModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_school_core_tables_columns_defaults_and_casts_are_available(): void
    {
        $this->assertTrue(Schema::hasColumns('school_locations', [
            'id',
            'name',
            'locality',
            'address',
            'is_active',
            'sort_order',
            'admin_notes',
        ]));
        $this->assertTrue(Schema::hasColumns('school_programs', [
            'id',
            'name',
            'is_public',
            'enrollments_open',
            'default_school_location_id',
            'contact_phone',
            'contact_email',
            'sort_order',
            'public_slot',
        ]));
        $this->assertTrue(Schema::hasColumns('school_levels', [
            'id',
            'school_program_id',
            'name',
            'minimum_age',
            'maximum_age',
            'is_active',
            'is_public',
            'sort_order',
        ]));
        $this->assertTrue(Schema::hasColumns('school_schedules', [
            'id',
            'school_level_id',
            'school_location_id',
            'day_of_week',
            'starts_at',
            'ends_at',
            'is_active',
            'sort_order',
        ]));

        $location = SchoolLocation::query()->create([
            'name' => 'Ubicación por defecto',
            'locality' => 'Localidad',
        ]);
        $program = SchoolProgram::query()->create([
            'name' => 'Programa por defecto',
        ]);
        $level = SchoolLevel::query()->create([
            'school_program_id' => $program->id,
            'name' => 'Nivel por defecto',
        ]);
        $schedule = SchoolSchedule::query()->create([
            'school_level_id' => $level->id,
            'school_location_id' => $location->id,
            'day_of_week' => SchoolDayOfWeek::MONDAY->value,
            'starts_at' => '17:00',
            'ends_at' => '18:00',
        ]);

        $this->assertFalse($location->fresh()->is_active);
        $this->assertSame(0, $location->fresh()->sort_order);
        $this->assertFalse($program->fresh()->is_public);
        $this->assertFalse($program->fresh()->enrollments_open);
        $this->assertSame(0, $program->fresh()->sort_order);
        $this->assertFalse($level->fresh()->is_active);
        $this->assertFalse($level->fresh()->is_public);
        $this->assertSame(0, $level->fresh()->sort_order);
        $this->assertFalse($schedule->fresh()->is_active);
        $this->assertSame(SchoolDayOfWeek::MONDAY, $schedule->fresh()->day_of_week);
        $this->assertSame(0, $schedule->fresh()->sort_order);
    }

    public function test_factories_provide_valid_and_expressive_states(): void
    {
        $location = SchoolLocation::factory()->active()->create();
        $program = SchoolProgram::factory()
            ->publiclyVisible()
            ->enrollmentsOpen()
            ->create([
                'default_school_location_id' => $location->id,
            ]);
        $level = SchoolLevel::factory()
            ->for($program, 'program')
            ->active()
            ->publiclyVisible()
            ->create([
                'minimum_age' => 8,
                'maximum_age' => 17,
            ]);
        $schedule = SchoolSchedule::factory()
            ->for($level, 'level')
            ->for($location, 'location')
            ->onDay(SchoolDayOfWeek::FRIDAY)
            ->between('18:00', '19:30')
            ->active()
            ->create();

        $this->assertTrue($location->is_active);
        $this->assertTrue($program->is_public);
        $this->assertTrue($program->enrollments_open);
        $this->assertTrue($level->is_active);
        $this->assertTrue($level->is_public);
        $this->assertSame(SchoolDayOfWeek::FRIDAY, $schedule->day_of_week);
        $this->assertTrue($schedule->is_active);

        $this->assertFalse(SchoolLocation::factory()->inactive()->create()->is_active);
        $this->assertFalse(SchoolProgram::factory()->privatelyVisible()->create()->is_public);
        $this->assertFalse(SchoolLevel::factory()->inactive()->create()->is_active);
        $this->assertFalse(SchoolSchedule::factory()->inactive()->create()->is_active);
    }

    public function test_relations_are_connected_without_reusing_venue(): void
    {
        $location = SchoolLocation::factory()->active()->create();
        $program = SchoolProgram::factory()->create([
            'default_school_location_id' => $location->id,
        ]);
        $level = SchoolLevel::factory()->for($program, 'program')->create();
        $schedule = SchoolSchedule::factory()
            ->for($level, 'level')
            ->for($location, 'location')
            ->create();

        $this->assertTrue($program->defaultLocation->is($location));
        $this->assertTrue($program->levels->contains($level));
        $this->assertTrue($level->program->is($program));
        $this->assertTrue($level->schedules->contains($schedule));
        $this->assertTrue($schedule->level->is($level));
        $this->assertTrue($schedule->location->is($location));
        $this->assertTrue($location->defaultForPrograms->contains($program));
        $this->assertTrue($location->schedules->contains($schedule));
        $this->assertFalse(Schema::hasColumn('school_locations', 'venue_id'));
        $this->assertFalse(Schema::hasColumn('school_schedules', 'venue_id'));
    }

    public function test_database_constraint_allows_at_most_one_public_program(): void
    {
        SchoolProgram::factory()->publiclyVisible()->create();

        $this->expectException(QueryException::class);

        SchoolProgram::factory()->publiclyVisible()->create();
    }

    public function test_program_service_returns_a_clear_error_for_a_second_public_program(): void
    {
        SchoolProgram::factory()->publiclyVisible()->create();
        $service = app(SchoolProgramService::class);

        try {
            $service->create([
                'name' => 'Segundo público',
                'is_public' => true,
                'enrollments_open' => false,
                'default_school_location_id' => null,
                'contact_phone' => null,
                'contact_email' => null,
                'sort_order' => 0,
            ]);
            $this->fail('La publicación exclusiva debería haber rechazado el segundo programa.');
        } catch (ValidationException $exception) {
            $this->assertSame(
                SchoolProgramService::PUBLICATION_ERROR,
                $exception->errors()['is_public'][0]
            );
        }
    }

    public function test_database_constraint_rejects_an_exact_duplicate_schedule(): void
    {
        $level = SchoolLevel::factory()->active()->create();
        $location = SchoolLocation::factory()->active()->create();
        $attributes = [
            'school_level_id' => $level->id,
            'school_location_id' => $location->id,
            'day_of_week' => SchoolDayOfWeek::WEDNESDAY->value,
            'starts_at' => '17:00',
            'ends_at' => '18:00',
            'is_active' => false,
            'sort_order' => 0,
        ];

        SchoolSchedule::query()->create($attributes);

        $this->expectException(QueryException::class);

        SchoolSchedule::query()->create($attributes);
    }

    public function test_effective_visibility_scopes_apply_the_complete_hierarchy(): void
    {
        $publicProgram = SchoolProgram::factory()->publiclyVisible()->create();
        $privateProgram = SchoolProgram::factory()->create();
        $activeLocation = SchoolLocation::factory()->active()->create();
        $inactiveLocation = SchoolLocation::factory()->inactive()->create();

        $publicLevel = SchoolLevel::factory()
            ->for($publicProgram, 'program')
            ->active()
            ->publiclyVisible()
            ->create();
        $privateLevel = SchoolLevel::factory()
            ->for($publicProgram, 'program')
            ->active()
            ->create();
        $inactiveLevel = SchoolLevel::factory()
            ->for($publicProgram, 'program')
            ->publiclyVisible()
            ->create();
        $orphanedPublicLevel = SchoolLevel::factory()
            ->for($privateProgram, 'program')
            ->active()
            ->publiclyVisible()
            ->create();

        $visibleSchedule = SchoolSchedule::factory()
            ->for($publicLevel, 'level')
            ->for($activeLocation, 'location')
            ->active()
            ->between('09:00', '10:00')
            ->create();
        SchoolSchedule::factory()
            ->for($privateLevel, 'level')
            ->for($activeLocation, 'location')
            ->active()
            ->between('10:00', '11:00')
            ->create();
        SchoolSchedule::factory()
            ->for($inactiveLevel, 'level')
            ->for($activeLocation, 'location')
            ->active()
            ->between('11:00', '12:00')
            ->create();
        SchoolSchedule::factory()
            ->for($orphanedPublicLevel, 'level')
            ->for($activeLocation, 'location')
            ->active()
            ->between('12:00', '13:00')
            ->create();
        SchoolSchedule::factory()
            ->for($publicLevel, 'level')
            ->for($inactiveLocation, 'location')
            ->active()
            ->between('13:00', '14:00')
            ->create();
        SchoolSchedule::factory()
            ->for($publicLevel, 'level')
            ->for($activeLocation, 'location')
            ->inactive()
            ->between('14:00', '15:00')
            ->create();

        $this->assertEquals(
            [$publicProgram->id],
            SchoolProgram::query()->effectivelyPublic()->pluck('id')->all()
        );
        $this->assertEquals(
            [$publicLevel->id],
            SchoolLevel::query()->effectivelyPublic()->pluck('id')->all()
        );
        $this->assertEquals(
            [$visibleSchedule->id],
            SchoolSchedule::query()->effectivelyPublic()->pluck('id')->all()
        );
        $this->assertTrue($visibleSchedule->load('level.program', 'location')->isEffectivelyPublic());
        $this->assertFalse($orphanedPublicLevel->load('program')->isEffectivelyPublic());
    }

    public function test_hiding_a_parent_preserves_child_flags_but_removes_effective_visibility(): void
    {
        $program = SchoolProgram::factory()->publiclyVisible()->create();
        $location = SchoolLocation::factory()->active()->create();
        $level = SchoolLevel::factory()
            ->for($program, 'program')
            ->active()
            ->publiclyVisible()
            ->create();
        $schedule = SchoolSchedule::factory()
            ->for($level, 'level')
            ->for($location, 'location')
            ->active()
            ->create();

        $program->update(['is_public' => false]);

        $this->assertTrue($level->fresh()->is_public);
        $this->assertTrue($schedule->fresh()->is_active);
        $this->assertFalse(
            SchoolSchedule::query()->effectivelyPublic()->whereKey($schedule->id)->exists()
        );
    }

    public function test_database_foreign_keys_restrict_destructive_parent_deletes(): void
    {
        $location = SchoolLocation::factory()->active()->create();
        $program = SchoolProgram::factory()->create([
            'default_school_location_id' => $location->id,
        ]);
        $level = SchoolLevel::factory()->for($program, 'program')->create();
        SchoolSchedule::factory()
            ->for($level, 'level')
            ->for($location, 'location')
            ->create();

        foreach ([$program, $level, $location] as $model) {
            try {
                $model->delete();
                $this->fail('La clave foránea debería haber protegido el borrado.');
            } catch (QueryException) {
                $this->assertDatabaseHas($model->getTable(), ['id' => $model->id]);
            }
        }
    }

    public function test_ordered_scopes_use_sort_order_and_id_as_stable_tiebreaker(): void
    {
        $first = SchoolLocation::factory()->create(['sort_order' => 10]);
        $second = SchoolLocation::factory()->create(['sort_order' => 5]);
        $third = SchoolLocation::factory()->create(['sort_order' => 10]);

        $this->assertEquals(
            [$second->id, $first->id, $third->id],
            SchoolLocation::query()->ordered()->pluck('id')->all()
        );
    }
}
