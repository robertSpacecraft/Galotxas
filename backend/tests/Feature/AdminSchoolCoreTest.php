<?php

namespace Tests\Feature;

use App\Enums\SchoolDayOfWeek;
use App\Models\SchoolLevel;
use App\Models\SchoolLocation;
use App\Models\SchoolProgram;
use App\Models\SchoolSchedule;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminSchoolCoreTest extends TestCase
{
    use RefreshDatabase;

    public function test_active_admin_can_access_all_school_sections_and_navigation(): void
    {
        $admin = User::factory()->admin()->create();

        foreach ([
            'admin.school.programs.index',
            'admin.school.levels.index',
            'admin.school.locations.index',
            'admin.school.schedules.index',
        ] as $routeName) {
            $this->actingAs($admin)->get(route($routeName))->assertOk();
        }

        $this->actingAs($admin)
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee('Escuela de Galotxas')
            ->assertSee(route('admin.school.programs.index'))
            ->assertSee(route('admin.school.levels.index'))
            ->assertSee(route('admin.school.locations.index'))
            ->assertSee(route('admin.school.schedules.index'));
    }

    public function test_admin_sees_empty_states_and_can_open_creation_forms(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->get(route('admin.school.programs.index'))
            ->assertSee('No hay programas de Escuela registrados.');
        $this->actingAs($admin)
            ->get(route('admin.school.levels.index'))
            ->assertSee('No hay niveles registrados para este contexto.');
        $this->actingAs($admin)
            ->get(route('admin.school.locations.index'))
            ->assertSee('No hay ubicaciones escolares registradas.');
        $this->actingAs($admin)
            ->get(route('admin.school.schedules.index'))
            ->assertSee('No hay horarios registrados para este contexto.');

        $this->actingAs($admin)
            ->get(route('admin.school.programs.create'))
            ->assertOk()
            ->assertSee('Crear programa de Escuela');
        $this->actingAs($admin)
            ->get(route('admin.school.locations.create'))
            ->assertOk()
            ->assertSee('Crear ubicación escolar');
        $this->actingAs($admin)
            ->get(route('admin.school.levels.create'))
            ->assertOk()
            ->assertSee('Debes crear un programa de Escuela');
        $this->actingAs($admin)
            ->get(route('admin.school.schedules.create'))
            ->assertOk()
            ->assertSee('Debes disponer de al menos un nivel y una ubicación escolar');

        $program = SchoolProgram::factory()->create();
        $level = SchoolLevel::factory()->for($program, 'program')->create();
        SchoolLocation::factory()->create();

        $this->actingAs($admin)
            ->get(route('admin.school.levels.create', ['program' => $program->id]))
            ->assertOk()
            ->assertSee('name="school_program_id"', false);
        $this->actingAs($admin)
            ->get(route('admin.school.schedules.create', ['level' => $level->id]))
            ->assertOk()
            ->assertSee('name="school_level_id"', false);
    }

    public function test_school_routes_reject_non_admin_anonymous_and_inactive_admin_users(): void
    {
        $user = User::factory()->create();
        $inactiveAdmin = User::factory()->admin()->create(['active' => false]);

        foreach ([
            'admin.school.programs.index',
            'admin.school.levels.index',
            'admin.school.locations.index',
            'admin.school.schedules.index',
        ] as $routeName) {
            $this->get(route($routeName))->assertRedirect(route('admin.login'));
            $this->actingAs($user)->get(route($routeName))->assertForbidden();
            $this->actingAs($inactiveAdmin)
                ->get(route($routeName))
                ->assertRedirect(route('admin.login'));
        }
    }

    public function test_admin_creates_a_private_closed_program_with_controlled_fields(): void
    {
        $admin = User::factory()->admin()->create();

        $response = $this->actingAs($admin)
            ->post(route('admin.school.programs.store'), $this->programPayload([
                'name' => '  Escuela permanente  ',
                'is_public' => '0',
                'enrollments_open' => '0',
                'contact_phone' => '',
                'contact_email' => '',
                'public_slot' => 99,
                'methodology' => 'No debe persistirse',
            ]));

        $response
            ->assertRedirect(route('admin.school.programs.index'))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('school_programs', [
            'name' => 'Escuela permanente',
            'is_public' => false,
            'enrollments_open' => false,
            'contact_phone' => null,
            'contact_email' => null,
            'sort_order' => 0,
        ]);
    }

    public function test_admin_publishes_and_updates_the_single_public_program(): void
    {
        $admin = User::factory()->admin()->create();
        $location = SchoolLocation::factory()->active()->create();

        $this->actingAs($admin)
            ->post(route('admin.school.programs.store'), $this->programPayload([
                'name' => 'Programa público',
                'is_public' => '1',
                'enrollments_open' => '1',
                'default_school_location_id' => $location->id,
                'contact_phone' => '600 000 000',
                'contact_email' => 'escuela@example.test',
            ]))
            ->assertRedirect(route('admin.school.programs.index'))
            ->assertSessionHasNoErrors();

        $program = SchoolProgram::query()->sole();

        $this->actingAs($admin)
            ->put(
                route('admin.school.programs.update', $program),
                $this->programPayload([
                    'name' => 'Programa público actualizado',
                    'is_public' => '1',
                    'enrollments_open' => '0',
                    'default_school_location_id' => $location->id,
                ])
            )
            ->assertRedirect(route('admin.school.programs.index'))
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('school_programs', [
            'id' => $program->id,
            'name' => 'Programa público actualizado',
            'is_public' => true,
            'enrollments_open' => false,
            'default_school_location_id' => $location->id,
        ]);

        $this->actingAs($admin)
            ->put(
                route('admin.school.programs.update', $program),
                $this->programPayload([
                    'name' => 'Programa privado temporal',
                    'is_public' => '0',
                    'enrollments_open' => '1',
                    'default_school_location_id' => $location->id,
                ])
            )
            ->assertSessionHasNoErrors();

        $this->assertFalse($program->fresh()->is_public);
        $this->assertTrue($program->fresh()->enrollments_open);
        $this->assertFalse($program->fresh()->acceptsPublicEnrollments());
    }

    public function test_second_public_program_and_inactive_default_location_are_rejected(): void
    {
        $admin = User::factory()->admin()->create();
        $activeLocation = SchoolLocation::factory()->active()->create();
        $inactiveLocation = SchoolLocation::factory()->inactive()->create();
        SchoolProgram::factory()->publiclyVisible()->create([
            'default_school_location_id' => $activeLocation->id,
        ]);

        $this->actingAs($admin)
            ->post(route('admin.school.programs.store'), $this->programPayload([
                'name' => 'Segundo público',
                'is_public' => '1',
                'default_school_location_id' => $activeLocation->id,
            ]))
            ->assertSessionHasErrors('is_public');

        SchoolProgram::query()->update(['is_public' => false]);

        $this->actingAs($admin)
            ->post(route('admin.school.programs.store'), $this->programPayload([
                'name' => 'Ubicación inactiva',
                'is_public' => '1',
                'default_school_location_id' => $inactiveLocation->id,
            ]))
            ->assertSessionHasErrors('default_school_location_id');

        $this->assertSame(0, SchoolProgram::query()->effectivelyPublic()->count());
    }

    public function test_program_validation_preserves_old_input_and_rejects_invalid_email(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->from(route('admin.school.programs.create'))
            ->post(route('admin.school.programs.store'), $this->programPayload([
                'name' => '',
                'contact_email' => 'correo-invalido',
            ]))
            ->assertRedirect(route('admin.school.programs.create'))
            ->assertSessionHasErrors(['name', 'contact_email'])
            ->assertSessionHasInput('contact_email', 'correo-invalido');
    }

    public function test_program_deletion_is_allowed_without_levels_and_blocked_with_levels(): void
    {
        $admin = User::factory()->admin()->create();
        $unused = SchoolProgram::factory()->create();
        $used = SchoolProgram::factory()->create();
        SchoolLevel::factory()->for($used, 'program')->create();

        $this->actingAs($admin)
            ->delete(route('admin.school.programs.destroy', $unused))
            ->assertSessionHas('success');
        $this->assertDatabaseMissing('school_programs', ['id' => $unused->id]);

        $this->actingAs($admin)
            ->delete(route('admin.school.programs.destroy', $used))
            ->assertSessionHas('error');
        $this->assertDatabaseHas('school_programs', ['id' => $used->id]);
    }

    public function test_admin_creates_updates_and_filters_levels(): void
    {
        $admin = User::factory()->admin()->create();
        $program = SchoolProgram::factory()->publiclyVisible()->create();
        $otherProgram = SchoolProgram::factory()->create();

        $this->actingAs($admin)
            ->post(route('admin.school.levels.store'), $this->levelPayload($program, [
                'name' => '  Infantil y juvenil  ',
                'minimum_age' => '8',
                'maximum_age' => '17',
                'is_active' => '1',
                'is_public' => '1',
                'sort_order' => 20,
                'slug' => 'no-se-persistira',
            ]))
            ->assertRedirect(route('admin.school.levels.index'))
            ->assertSessionHasNoErrors();

        $level = SchoolLevel::query()->sole();
        $this->assertDatabaseHas('school_levels', [
            'id' => $level->id,
            'school_program_id' => $program->id,
            'name' => 'Infantil y juvenil',
            'minimum_age' => 8,
            'maximum_age' => 17,
            'is_active' => true,
            'is_public' => true,
            'sort_order' => 20,
        ]);

        $this->actingAs($admin)
            ->put(
                route('admin.school.levels.update', $level),
                $this->levelPayload($program, [
                    'name' => 'Nivel actualizado',
                    'minimum_age' => '',
                    'maximum_age' => '',
                    'is_active' => '0',
                    'is_public' => '0',
                ])
            )
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('school_levels', [
            'id' => $level->id,
            'name' => 'Nivel actualizado',
            'minimum_age' => null,
            'maximum_age' => null,
            'is_active' => false,
            'is_public' => false,
        ]);

        SchoolLevel::factory()->for($otherProgram, 'program')->create([
            'name' => 'Nivel de otro programa',
        ]);

        $this->actingAs($admin)
            ->get(route('admin.school.levels.index', ['program' => $program->id]))
            ->assertOk()
            ->assertSee('Nivel actualizado')
            ->assertDontSee('Nivel de otro programa');
    }

    public function test_level_validation_checks_program_age_range_and_public_parent(): void
    {
        $admin = User::factory()->admin()->create();
        $privateProgram = SchoolProgram::factory()->create();

        $this->actingAs($admin)
            ->post(route('admin.school.levels.store'), $this->levelPayload($privateProgram, [
                'minimum_age' => 18,
                'maximum_age' => 8,
                'is_public' => '1',
            ]))
            ->assertSessionHasErrors(['minimum_age', 'is_public']);

        $this->actingAs($admin)
            ->post(route('admin.school.levels.store'), [
                'school_program_id' => 999999,
                'name' => 'Inválido',
                'minimum_age' => null,
                'maximum_age' => null,
                'is_active' => '0',
                'is_public' => '0',
                'sort_order' => 0,
            ])
            ->assertSessionHasErrors('school_program_id');
    }

    public function test_level_deletion_is_blocked_when_it_has_schedules(): void
    {
        $admin = User::factory()->admin()->create();
        $level = SchoolLevel::factory()->active()->create();
        $location = SchoolLocation::factory()->active()->create();
        SchoolSchedule::factory()
            ->for($level, 'level')
            ->for($location, 'location')
            ->create();

        $this->actingAs($admin)
            ->delete(route('admin.school.levels.destroy', $level))
            ->assertSessionHas('error');

        $this->assertDatabaseHas('school_levels', ['id' => $level->id]);
    }

    public function test_admin_manages_locations_and_private_notes(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->post(route('admin.school.locations.store'), $this->locationPayload([
                'name' => '  Pabellón escolar  ',
                'locality' => '  Monóvar  ',
                'address' => '',
                'is_active' => '1',
                'sort_order' => 5,
                'admin_notes' => '  Acceso por recepción.  ',
                'url' => 'https://example.test',
            ]))
            ->assertRedirect(route('admin.school.locations.index'))
            ->assertSessionHasNoErrors();

        $location = SchoolLocation::query()->sole();
        $this->assertDatabaseHas('school_locations', [
            'id' => $location->id,
            'name' => 'Pabellón escolar',
            'locality' => 'Monóvar',
            'address' => null,
            'is_active' => true,
            'sort_order' => 5,
            'admin_notes' => 'Acceso por recepción.',
        ]);

        $this->actingAs($admin)
            ->put(
                route('admin.school.locations.update', $location),
                $this->locationPayload([
                    'name' => 'Pabellón actualizado',
                    'locality' => 'Elda',
                    'is_active' => '0',
                    'admin_notes' => '',
                ])
            )
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('school_locations', [
            'id' => $location->id,
            'name' => 'Pabellón actualizado',
            'locality' => 'Elda',
            'is_active' => false,
            'admin_notes' => null,
        ]);
    }

    public function test_location_requires_locality_and_cannot_be_deleted_while_in_use(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->post(route('admin.school.locations.store'), $this->locationPayload([
                'locality' => '',
            ]))
            ->assertSessionHasErrors('locality');

        $location = SchoolLocation::factory()->active()->create();
        SchoolProgram::factory()->create([
            'default_school_location_id' => $location->id,
        ]);

        $this->actingAs($admin)
            ->delete(route('admin.school.locations.destroy', $location))
            ->assertSessionHas('error');

        $this->assertDatabaseHas('school_locations', ['id' => $location->id]);
    }

    public function test_unused_level_and_location_can_be_deleted(): void
    {
        $admin = User::factory()->admin()->create();
        $level = SchoolLevel::factory()->create();
        $location = SchoolLocation::factory()->create();

        $this->actingAs($admin)
            ->delete(route('admin.school.levels.destroy', $level))
            ->assertSessionHas('success');
        $this->actingAs($admin)
            ->delete(route('admin.school.locations.destroy', $location))
            ->assertSessionHas('success');

        $this->assertDatabaseMissing('school_levels', ['id' => $level->id]);
        $this->assertDatabaseMissing('school_locations', ['id' => $location->id]);
    }

    public function test_admin_creates_updates_filters_and_deletes_a_schedule(): void
    {
        $admin = User::factory()->admin()->create();
        $program = SchoolProgram::factory()->publiclyVisible()->create();
        $level = SchoolLevel::factory()
            ->for($program, 'program')
            ->active()
            ->publiclyVisible()
            ->create();
        $location = SchoolLocation::factory()->active()->create();

        $this->actingAs($admin)
            ->post(
                route('admin.school.schedules.store'),
                $this->schedulePayload($level, $location, [
                    'day_of_week' => '5',
                    'starts_at' => '17:00',
                    'ends_at' => '18:30',
                    'is_active' => '1',
                    'sort_order' => 10,
                    'calendar_date' => '2026-09-01',
                ])
            )
            ->assertRedirect(route('admin.school.schedules.index'))
            ->assertSessionHasNoErrors();

        $schedule = SchoolSchedule::query()->sole();
        $this->assertDatabaseHas('school_schedules', [
            'id' => $schedule->id,
            'school_level_id' => $level->id,
            'school_location_id' => $location->id,
            'day_of_week' => 5,
            'starts_at' => '17:00:00',
            'ends_at' => '18:30:00',
            'is_active' => true,
            'sort_order' => 10,
        ]);

        $this->actingAs($admin)
            ->get(route('admin.school.schedules.index', [
                'program' => $program->id,
                'level' => $level->id,
            ]))
            ->assertOk()
            ->assertSee('Viernes')
            ->assertSee('17:00–18:30');

        $this->actingAs($admin)
            ->put(
                route('admin.school.schedules.update', $schedule),
                $this->schedulePayload($level, $location, [
                    'day_of_week' => 6,
                    'starts_at' => '10:00',
                    'ends_at' => '11:00',
                    'is_active' => '0',
                ])
            )
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('school_schedules', [
            'id' => $schedule->id,
            'day_of_week' => 6,
            'starts_at' => '10:00:00',
            'ends_at' => '11:00:00',
            'is_active' => false,
        ]);

        $this->actingAs($admin)
            ->delete(route('admin.school.schedules.destroy', $schedule))
            ->assertSessionHas('success');
        $this->assertDatabaseMissing('school_schedules', ['id' => $schedule->id]);
    }

    public function test_schedule_validation_rejects_day_time_duplicate_and_inactive_relations(): void
    {
        $admin = User::factory()->admin()->create();
        $activeLevel = SchoolLevel::factory()->active()->create();
        $inactiveLevel = SchoolLevel::factory()->inactive()->create();
        $activeLocation = SchoolLocation::factory()->active()->create();
        $inactiveLocation = SchoolLocation::factory()->inactive()->create();

        $this->actingAs($admin)
            ->post(
                route('admin.school.schedules.store'),
                $this->schedulePayload($activeLevel, $activeLocation, [
                    'day_of_week' => 8,
                    'starts_at' => '18:00',
                    'ends_at' => '17:00',
                ])
            )
            ->assertSessionHasErrors(['day_of_week', 'ends_at']);

        SchoolSchedule::factory()
            ->for($activeLevel, 'level')
            ->for($activeLocation, 'location')
            ->onDay(SchoolDayOfWeek::MONDAY)
            ->between('17:00', '18:00')
            ->create();

        $this->actingAs($admin)
            ->post(
                route('admin.school.schedules.store'),
                $this->schedulePayload($activeLevel, $activeLocation)
            )
            ->assertSessionHasErrors('starts_at');

        $this->actingAs($admin)
            ->post(
                route('admin.school.schedules.store'),
                $this->schedulePayload($inactiveLevel, $inactiveLocation, [
                    'is_active' => '1',
                    'starts_at' => '19:00',
                    'ends_at' => '20:00',
                ])
            )
            ->assertSessionHasErrors(['school_level_id', 'school_location_id']);
    }

    public function test_partially_overlapping_school_schedules_are_allowed(): void
    {
        $admin = User::factory()->admin()->create();
        $level = SchoolLevel::factory()->active()->create();
        $location = SchoolLocation::factory()->active()->create();
        SchoolSchedule::factory()
            ->for($level, 'level')
            ->for($location, 'location')
            ->onDay(SchoolDayOfWeek::TUESDAY)
            ->between('17:00', '18:00')
            ->create();

        $this->actingAs($admin)
            ->post(
                route('admin.school.schedules.store'),
                $this->schedulePayload($level, $location, [
                    'day_of_week' => SchoolDayOfWeek::TUESDAY->value,
                    'starts_at' => '17:30',
                    'ends_at' => '18:30',
                ])
            )
            ->assertRedirect(route('admin.school.schedules.index'))
            ->assertSessionHasNoErrors();

        $this->assertSame(2, SchoolSchedule::query()->count());
    }

    public function test_public_school_api_exists_without_public_web_route(): void
    {
        $this->getJson('/api/v1/school')
            ->assertOk()
            ->assertExactJson([
                'message' => null,
                'data' => null,
            ]);
        $this->get('/escuela')->assertNotFound();
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function programPayload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Programa escolar',
            'is_public' => '0',
            'enrollments_open' => '0',
            'default_school_location_id' => null,
            'contact_phone' => null,
            'contact_email' => null,
            'sort_order' => 0,
        ], $overrides);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function levelPayload(SchoolProgram $program, array $overrides = []): array
    {
        return array_merge([
            'school_program_id' => $program->id,
            'name' => 'Nivel escolar',
            'minimum_age' => null,
            'maximum_age' => null,
            'is_active' => '0',
            'is_public' => '0',
            'sort_order' => 0,
        ], $overrides);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function locationPayload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Ubicación escolar',
            'locality' => 'Localidad',
            'address' => null,
            'is_active' => '0',
            'sort_order' => 0,
            'admin_notes' => null,
        ], $overrides);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function schedulePayload(
        SchoolLevel $level,
        SchoolLocation $location,
        array $overrides = []
    ): array {
        return array_merge([
            'school_level_id' => $level->id,
            'school_location_id' => $location->id,
            'day_of_week' => SchoolDayOfWeek::MONDAY->value,
            'starts_at' => '17:00',
            'ends_at' => '18:00',
            'is_active' => '0',
            'sort_order' => 0,
        ], $overrides);
    }
}
