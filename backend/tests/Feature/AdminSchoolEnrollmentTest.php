<?php

namespace Tests\Feature;

use App\Enums\SchoolEnrollmentStatus;
use App\Models\SchoolEnrollment;
use App\Models\SchoolLevel;
use App\Models\SchoolProgram;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class AdminSchoolEnrollmentTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-07-28 10:15:00');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_enrollment_routes_require_active_administrator(): void
    {
        $enrollment = SchoolEnrollment::factory()->create();
        $user = User::factory()->create();
        $inactiveAdmin = User::factory()->admin()->create(['active' => false]);
        $admin = User::factory()->admin()->create();

        $this->get(route('admin.school.enrollments.index'))
            ->assertRedirect(route('admin.login'));
        $this->actingAs($user)
            ->get(route('admin.school.enrollments.index'))
            ->assertForbidden();
        $this->actingAs($inactiveAdmin)
            ->get(route('admin.school.enrollments.index'))
            ->assertRedirect(route('admin.login'));

        $this->actingAs($user)
            ->post(route('admin.school.enrollments.reject', $enrollment))
            ->assertForbidden();
        $this->actingAs($inactiveAdmin)
            ->post(route('admin.school.enrollments.reject', $enrollment))
            ->assertRedirect(route('admin.login'));

        $this->actingAs($admin)
            ->get(route('admin.school.enrollments.index'))
            ->assertOk();
    }

    public function test_navigation_empty_state_counters_and_manual_form_are_available(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee('Inscripciones')
            ->assertSee(route('admin.school.enrollments.index'));

        $this->actingAs($admin)
            ->get(route('admin.school.enrollments.index'))
            ->assertOk()
            ->assertSee('Pendiente')
            ->assertSee('Activa')
            ->assertSee('Rechazada')
            ->assertSee('Baja')
            ->assertSee('No hay inscripciones para este contexto.');

        $this->actingAs($admin)
            ->get(route('admin.school.enrollments.create'))
            ->assertOk()
            ->assertSee('Debes crear un programa de Escuela');
    }

    public function test_active_admin_creates_manual_pending_enrollment_without_account(): void
    {
        $admin = User::factory()->admin()->create();
        $program = SchoolProgram::factory()->create();
        $level = SchoolLevel::factory()
            ->for($program, 'program')
            ->active()
            ->create();

        $response = $this->actingAs($admin)
            ->post(
                route('admin.school.enrollments.store'),
                $this->adultPayload([
                    'school_program_id' => $program->id,
                    'school_level_id' => $level->id,
                    'participant_name' => '  Alta manual  ',
                    'contact_email' => '  MANUAL@EXAMPLE.TEST ',
                    'guardian_name' => 'No procede',
                    'guardian_relationship' => 'No procede',
                    'admin_notes' => '  Nota interna  ',
                    'user_id' => User::factory()->create()->id,
                    'status' => 'active',
                    'activated_at' => '2000-01-01 00:00:00',
                ])
            );

        $enrollment = SchoolEnrollment::query()->sole();

        $response
            ->assertRedirect(route('admin.school.enrollments.show', $enrollment))
            ->assertSessionHas('success');
        $this->assertSame(SchoolEnrollmentStatus::PENDING, $enrollment->status);
        $this->assertSame($program->id, $enrollment->school_program_id);
        $this->assertSame($level->id, $enrollment->school_level_id);
        $this->assertNull($enrollment->user_id);
        $this->assertSame('Alta manual', $enrollment->participant_name);
        $this->assertSame('manual@example.test', $enrollment->contact_email);
        $this->assertNull($enrollment->guardian_name);
        $this->assertSame('Nota interna', $enrollment->admin_notes);
        $this->assertSame('2026-07-28 10:15:00', $enrollment->requested_at->format('Y-m-d H:i:s'));
        $this->assertNull($enrollment->activated_at);
    }

    public function test_manual_creation_validates_minor_and_program_level_consistency_with_old_input(): void
    {
        $admin = User::factory()->admin()->create();
        $program = SchoolProgram::factory()->create();
        $otherLevel = SchoolLevel::factory()->active()->create();

        $this->actingAs($admin)
            ->from(route('admin.school.enrollments.create'))
            ->post(
                route('admin.school.enrollments.store'),
                $this->minorPayload([
                    'school_program_id' => $program->id,
                    'school_level_id' => $otherLevel->id,
                    'participant_name' => 'Valor conservado',
                    'guardian_name' => '',
                    'guardian_relationship' => '',
                ])
            )
            ->assertRedirect(route('admin.school.enrollments.create'))
            ->assertSessionHasErrors([
                'school_level_id',
                'guardian_name',
                'guardian_relationship',
            ])
            ->assertSessionHasInput('participant_name', 'Valor conservado');

        $this->assertDatabaseCount('school_enrollments', 0);
    }

    public function test_index_filters_by_program_level_and_status_with_stable_order(): void
    {
        $admin = User::factory()->admin()->create();
        $program = SchoolProgram::factory()->create();
        $level = SchoolLevel::factory()
            ->for($program, 'program')
            ->active()
            ->create();
        SchoolEnrollment::factory()
            ->for($program, 'program')
            ->assignedToLevel($level)
            ->pending()
            ->create([
                'participant_name' => 'Coincide antigua',
                'requested_at' => '2026-07-01 10:00:00',
            ]);
        SchoolEnrollment::factory()
            ->for($program, 'program')
            ->assignedToLevel($level)
            ->pending()
            ->create([
                'participant_name' => 'Coincide reciente',
                'requested_at' => '2026-07-02 10:00:00',
            ]);
        SchoolEnrollment::factory()->active()->create([
            'participant_name' => 'No coincide estado',
        ]);
        SchoolEnrollment::factory()->pending()->create([
            'participant_name' => 'No coincide programa',
        ]);

        $this->actingAs($admin)
            ->get(route('admin.school.enrollments.index', [
                'program' => $program->id,
                'level' => $level->id,
                'status' => SchoolEnrollmentStatus::PENDING->value,
            ]))
            ->assertOk()
            ->assertSeeInOrder(['Coincide reciente', 'Coincide antigua'])
            ->assertDontSee('No coincide estado')
            ->assertDontSee('No coincide programa');
    }

    public function test_detail_displays_private_data_and_only_actions_valid_for_state(): void
    {
        $admin = User::factory()->admin()->create();
        $pending = SchoolEnrollment::factory()->minor()->pending()->create([
            'admin_notes' => 'Nota privada',
        ]);
        $active = SchoolEnrollment::factory()->active()->create();
        $rejected = SchoolEnrollment::factory()->rejected()->create();

        $this->actingAs($admin)
            ->get(route('admin.school.enrollments.show', $pending))
            ->assertOk()
            ->assertSee($pending->participant_name)
            ->assertSee($pending->contact_email)
            ->assertSee('Menor de edad')
            ->assertSee('Nota privada')
            ->assertSee('Aprobar y activar')
            ->assertSee('Rechazar solicitud')
            ->assertDontSee('Dar de baja')
            ->assertDontSee('Reasignar nivel');

        $this->actingAs($admin)
            ->get(route('admin.school.enrollments.show', $active))
            ->assertOk()
            ->assertSee('Reasignar nivel')
            ->assertSee('Dar de baja')
            ->assertDontSee('Aprobar y activar')
            ->assertDontSee('Rechazar solicitud');

        $this->actingAs($admin)
            ->get(route('admin.school.enrollments.show', $rejected))
            ->assertOk()
            ->assertSee('No puede reactivarse')
            ->assertDontSee('Aprobar y activar')
            ->assertDontSee('Dar de baja');
    }

    public function test_edit_updates_only_participant_contact_guardian_and_notes(): void
    {
        $admin = User::factory()->admin()->create();
        $program = SchoolProgram::factory()->create();
        $otherProgram = SchoolProgram::factory()->create();
        $level = SchoolLevel::factory()
            ->for($program, 'program')
            ->active()
            ->create();
        $otherLevel = SchoolLevel::factory()
            ->for($otherProgram, 'program')
            ->active()
            ->create();
        $user = User::factory()->create();
        $enrollment = SchoolEnrollment::factory()
            ->active()
            ->assignedToLevel($level)
            ->linkedToUser($user)
            ->create();
        $requestedAt = $enrollment->requested_at;
        $activatedAt = $enrollment->activated_at;

        $this->actingAs($admin)
            ->put(
                route('admin.school.enrollments.update', $enrollment),
                $this->adultPayload([
                    'participant_name' => 'Nombre corregido',
                    'contact_phone' => '622 222 222',
                    'contact_email' => 'CORREGIDO@EXAMPLE.TEST',
                    'admin_notes' => 'Nueva nota',
                    'school_program_id' => $otherProgram->id,
                    'school_level_id' => $otherLevel->id,
                    'user_id' => null,
                    'status' => 'rejected',
                    'requested_at' => '2000-01-01 00:00:00',
                    'activated_at' => null,
                ])
            )
            ->assertRedirect(route('admin.school.enrollments.show', $enrollment))
            ->assertSessionHasNoErrors();

        $enrollment = $enrollment->fresh();

        $this->assertSame('Nombre corregido', $enrollment->participant_name);
        $this->assertSame('622 222 222', $enrollment->contact_phone);
        $this->assertSame('corregido@example.test', $enrollment->contact_email);
        $this->assertSame('Nueva nota', $enrollment->admin_notes);
        $this->assertSame($program->id, $enrollment->school_program_id);
        $this->assertSame($level->id, $enrollment->school_level_id);
        $this->assertSame($user->id, $enrollment->user_id);
        $this->assertSame(SchoolEnrollmentStatus::ACTIVE, $enrollment->status);
        $this->assertTrue($enrollment->requested_at->equalTo($requestedAt));
        $this->assertTrue($enrollment->activated_at->equalTo($activatedAt));
    }

    public function test_edit_revalidates_guardian_when_birth_changes_and_normalizes_adult(): void
    {
        $admin = User::factory()->admin()->create();
        $enrollment = SchoolEnrollment::factory()->adult()->create();

        $this->actingAs($admin)
            ->from(route('admin.school.enrollments.edit', $enrollment))
            ->put(
                route('admin.school.enrollments.update', $enrollment),
                $this->minorPayload([
                    'guardian_name' => '',
                    'guardian_relationship' => '',
                ])
            )
            ->assertRedirect(route('admin.school.enrollments.edit', $enrollment))
            ->assertSessionHasErrors([
                'guardian_name',
                'guardian_relationship',
            ])
            ->assertSessionHasInput('participant_birth_date', '2012-08-01');

        $minor = SchoolEnrollment::factory()->minor()->create();

        $this->actingAs($admin)
            ->put(
                route('admin.school.enrollments.update', $minor),
                $this->adultPayload([
                    'guardian_name' => 'Debe limpiarse',
                    'guardian_relationship' => 'Debe limpiarse',
                ])
            )
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('school_enrollments', [
            'id' => $minor->id,
            'guardian_name' => null,
            'guardian_relationship' => null,
        ]);
    }

    public function test_admin_approves_pending_with_required_active_same_program_level(): void
    {
        $admin = User::factory()->admin()->create();
        $program = SchoolProgram::factory()->create();
        $level = SchoolLevel::factory()
            ->for($program, 'program')
            ->active()
            ->privatelyVisible()
            ->create();
        $enrollment = SchoolEnrollment::factory()
            ->for($program, 'program')
            ->pending()
            ->create();

        $this->actingAs($admin)
            ->post(route('admin.school.enrollments.approve', $enrollment), [
                'school_level_id' => $level->id,
            ])
            ->assertRedirect(route('admin.school.enrollments.show', $enrollment))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('school_enrollments', [
            'id' => $enrollment->id,
            'school_level_id' => $level->id,
            'status' => SchoolEnrollmentStatus::ACTIVE->value,
            'activated_at' => '2026-07-28 10:15:00',
        ]);
    }

    public function test_admin_approval_rejects_missing_inactive_and_cross_program_level(): void
    {
        $admin = User::factory()->admin()->create();
        $program = SchoolProgram::factory()->create();
        $inactive = SchoolLevel::factory()
            ->for($program, 'program')
            ->inactive()
            ->create();
        $other = SchoolLevel::factory()->active()->create();
        $enrollment = SchoolEnrollment::factory()
            ->for($program, 'program')
            ->pending()
            ->create();

        foreach ([null, $inactive->id, $other->id] as $levelId) {
            $this->actingAs($admin)
                ->from(route('admin.school.enrollments.show', $enrollment))
                ->post(route('admin.school.enrollments.approve', $enrollment), [
                    'school_level_id' => $levelId,
                ])
                ->assertRedirect(route('admin.school.enrollments.show', $enrollment))
                ->assertSessionHasErrors('school_level_id');
        }

        $this->assertSame(SchoolEnrollmentStatus::PENDING, $enrollment->fresh()->status);
    }

    public function test_admin_rejects_pending_and_cannot_repeat_or_reactivate_transition(): void
    {
        $admin = User::factory()->admin()->create();
        $enrollment = SchoolEnrollment::factory()->pending()->create();
        $level = SchoolLevel::factory()
            ->for($enrollment->program, 'program')
            ->active()
            ->create();

        $this->actingAs($admin)
            ->post(route('admin.school.enrollments.reject', $enrollment))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('school_enrollments', [
            'id' => $enrollment->id,
            'status' => SchoolEnrollmentStatus::REJECTED->value,
            'activated_at' => null,
            'rejected_at' => '2026-07-28 10:15:00',
            'withdrawn_at' => null,
        ]);

        $this->actingAs($admin)
            ->from(route('admin.school.enrollments.show', $enrollment))
            ->post(route('admin.school.enrollments.reject', $enrollment))
            ->assertSessionHasErrors('status');
        $this->actingAs($admin)
            ->from(route('admin.school.enrollments.show', $enrollment))
            ->post(route('admin.school.enrollments.approve', $enrollment), [
                'school_level_id' => $level->id,
            ])
            ->assertSessionHasErrors('status');

        $this->assertSame(SchoolEnrollmentStatus::REJECTED, $enrollment->fresh()->status);
    }

    public function test_admin_reassigns_active_level_and_withdraws_preserving_activation(): void
    {
        $admin = User::factory()->admin()->create();
        $program = SchoolProgram::factory()->create();
        $firstLevel = SchoolLevel::factory()
            ->for($program, 'program')
            ->active()
            ->create();
        $secondLevel = SchoolLevel::factory()
            ->for($program, 'program')
            ->active()
            ->create();
        $enrollment = SchoolEnrollment::factory()
            ->active()
            ->assignedToLevel($firstLevel)
            ->create([
                'activated_at' => '2026-06-01 09:00:00',
            ]);

        $this->actingAs($admin)
            ->post(route('admin.school.enrollments.reassign-level', $enrollment), [
                'school_level_id' => $secondLevel->id,
            ])
            ->assertSessionHas('success');

        $this->assertDatabaseHas('school_enrollments', [
            'id' => $enrollment->id,
            'school_level_id' => $secondLevel->id,
            'status' => SchoolEnrollmentStatus::ACTIVE->value,
            'activated_at' => '2026-06-01 09:00:00',
        ]);

        $this->actingAs($admin)
            ->post(route('admin.school.enrollments.withdraw', $enrollment))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('school_enrollments', [
            'id' => $enrollment->id,
            'status' => SchoolEnrollmentStatus::WITHDRAWN->value,
            'activated_at' => '2026-06-01 09:00:00',
            'rejected_at' => null,
            'withdrawn_at' => '2026-07-28 10:15:00',
        ]);

        $this->actingAs($admin)
            ->from(route('admin.school.enrollments.show', $enrollment))
            ->post(route('admin.school.enrollments.withdraw', $enrollment))
            ->assertSessionHasErrors('status');
        $this->assertSame(SchoolEnrollmentStatus::WITHDRAWN, $enrollment->fresh()->status);
    }

    public function test_reassignment_rejects_pending_inactive_and_cross_program_levels(): void
    {
        $admin = User::factory()->admin()->create();
        $program = SchoolProgram::factory()->create();
        $activeLevel = SchoolLevel::factory()
            ->for($program, 'program')
            ->active()
            ->create();
        $inactiveLevel = SchoolLevel::factory()
            ->for($program, 'program')
            ->inactive()
            ->create();
        $otherLevel = SchoolLevel::factory()->active()->create();
        $active = SchoolEnrollment::factory()
            ->active()
            ->assignedToLevel($activeLevel)
            ->create();
        $pending = SchoolEnrollment::factory()
            ->for($program, 'program')
            ->pending()
            ->create();

        foreach ([$inactiveLevel->id, $otherLevel->id] as $levelId) {
            $this->actingAs($admin)
                ->post(route('admin.school.enrollments.reassign-level', $active), [
                    'school_level_id' => $levelId,
                ])
                ->assertSessionHasErrors('school_level_id');
        }

        $this->actingAs($admin)
            ->from(route('admin.school.enrollments.show', $pending))
            ->post(route('admin.school.enrollments.reassign-level', $pending), [
                'school_level_id' => $activeLevel->id,
            ])
            ->assertSessionHasErrors('status');

        $this->assertSame($activeLevel->id, $active->fresh()->school_level_id);
        $this->assertSame(SchoolEnrollmentStatus::PENDING, $pending->fresh()->status);
    }

    public function test_no_administrative_destroy_route_or_action_exists(): void
    {
        $admin = User::factory()->admin()->create();
        $enrollment = SchoolEnrollment::factory()->rejected()->create();

        $this->assertFalse(Route::has('admin.school.enrollments.destroy'));
        $this->actingAs($admin)
            ->delete('/admin/school/enrollments/'.$enrollment->id)
            ->assertMethodNotAllowed();
        $this->assertDatabaseHas('school_enrollments', ['id' => $enrollment->id]);
    }

    public function test_program_and_level_with_enrollments_cannot_be_deleted_from_blade(): void
    {
        $admin = User::factory()->admin()->create();
        $programWithoutLevel = SchoolProgram::factory()->create();
        SchoolEnrollment::factory()
            ->for($programWithoutLevel, 'program')
            ->create();

        $program = SchoolProgram::factory()->create();
        $level = SchoolLevel::factory()
            ->for($program, 'program')
            ->active()
            ->create();
        SchoolEnrollment::factory()
            ->for($program, 'program')
            ->assignedToLevel($level)
            ->create();

        $this->actingAs($admin)
            ->get(route('admin.school.programs.index'))
            ->assertOk()
            ->assertSee('El programa tiene niveles o inscripciones asociadas');
        $this->actingAs($admin)
            ->delete(route('admin.school.programs.destroy', $programWithoutLevel))
            ->assertSessionHas('error');

        $this->actingAs($admin)
            ->get(route('admin.school.levels.index'))
            ->assertOk()
            ->assertSee('El nivel tiene horarios o inscripciones asociadas');
        $this->actingAs($admin)
            ->delete(route('admin.school.levels.destroy', $level))
            ->assertSessionHas('error');

        $this->assertDatabaseHas('school_programs', ['id' => $programWithoutLevel->id]);
        $this->assertDatabaseHas('school_levels', ['id' => $level->id]);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function adultPayload(array $overrides = []): array
    {
        return array_merge([
            'participant_name' => 'Participante Adulto',
            'participant_birth_date' => '1990-01-01',
            'contact_phone' => '611 000 000',
            'contact_email' => 'adulto@example.test',
            'guardian_name' => '',
            'guardian_relationship' => '',
            'admin_notes' => '',
        ], $overrides);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function minorPayload(array $overrides = []): array
    {
        return array_merge([
            'participant_name' => 'Participante Menor',
            'participant_birth_date' => '2012-08-01',
            'contact_phone' => '600 123 123',
            'contact_email' => 'familia@example.test',
            'guardian_name' => 'Persona Tutora',
            'guardian_relationship' => 'Madre',
            'admin_notes' => '',
        ], $overrides);
    }
}
