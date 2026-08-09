<?php

namespace Tests\Feature;

use App\Enums\SchoolEnrollmentStatus;
use App\Models\SchoolEnrollment;
use App\Models\SchoolProgram;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SchoolOperationalReadinessTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        CarbonImmutable::setTestNow('2026-08-09 12:00:00');
    }

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();

        parent::tearDown();
    }

    public function test_complete_program_is_closed_by_default_and_opens_only_with_environment_flag(): void
    {
        $program = SchoolProgram::factory()
            ->operationallyReady()
            ->enrollmentsOpen()
            ->create();

        $this->getJson('/api/v1/school')
            ->assertOk()
            ->assertJsonPath('data.enrollment_status', 'closed')
            ->assertJsonPath('data.enrollments_open', false);

        $this->postJson('/api/v1/school/enrollments', $this->adultPayload())
            ->assertConflict();

        config(['school.enrollment_enabled' => true]);

        $this->getJson('/api/v1/school')
            ->assertOk()
            ->assertJsonPath('data.enrollment_status', 'open')
            ->assertJsonPath('data.enrollments_open', true);

        $program->update(['enrollments_open' => false]);

        $this->getJson('/api/v1/school')
            ->assertOk()
            ->assertJsonPath('data.enrollment_status', 'closed');
    }

    public function test_incomplete_public_program_is_unavailable_and_admin_cannot_declare_it_open(): void
    {
        config(['school.enrollment_enabled' => true]);
        $program = SchoolProgram::factory()->publiclyVisible()->create();

        $this->getJson('/api/v1/school')
            ->assertOk()
            ->assertJsonPath('data.enrollment_status', 'unavailable');

        $admin = User::factory()->admin()->create();
        $this->actingAs($admin)
            ->from(route('admin.school.programs.edit', $program))
            ->put(route('admin.school.programs.update', $program), [
                'name' => $program->name,
                'public_description' => null,
                'enrollment_information' => null,
                'is_public' => '1',
                'enrollments_open' => '1',
                'default_school_location_id' => null,
                'contact_phone' => null,
                'contact_email' => null,
                'sort_order' => 0,
            ])
            ->assertSessionHasErrors('enrollments_open');

        $this->assertFalse($program->fresh()->enrollments_open);
    }

    public function test_school_notice_is_exact_and_honeypot_is_silent_without_persisting(): void
    {
        config(['school.enrollment_enabled' => true]);
        SchoolProgram::factory()
            ->operationallyReady()
            ->enrollmentsOpen()
            ->create();

        $this->postJson('/api/v1/school/enrollments', $this->adultPayload([
            'privacy_notice_version' => '0.9.0',
        ]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('privacy_notice_version');

        $this->postJson('/api/v1/school/enrollments', $this->adultPayload([
            'website' => 'https://bot.example.test',
        ]))
            ->assertCreated()
            ->assertExactJson([
                'message' => 'La solicitud de inscripción se ha recibido correctamente.',
                'data' => null,
            ]);

        $this->assertDatabaseCount('school_enrollments', 0);
    }

    public function test_invalid_phone_is_rejected_and_public_request_sets_notice_and_six_month_retention(): void
    {
        config(['school.enrollment_enabled' => true]);
        SchoolProgram::factory()
            ->operationallyReady()
            ->enrollmentsOpen()
            ->create();

        $this->postJson('/api/v1/school/enrollments', $this->adultPayload([
            'contact_phone' => 'teléfono inválido',
        ]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('contact_phone');

        $this->postJson('/api/v1/school/enrollments', $this->adultPayload())
            ->assertCreated();

        $enrollment = SchoolEnrollment::query()->sole();
        $this->assertSame('NOTICE-SCHOOL-ENROLLMENT', $enrollment->privacy_notice_id);
        $this->assertSame('1.0.0', $enrollment->privacy_notice_version);
        $this->assertSame(
            '2027-02-09 12:00:00',
            $enrollment->retention_until->format('Y-m-d H:i:s')
        );
    }

    public function test_admin_transitions_record_actor_and_apply_approved_retention_periods(): void
    {
        $admin = User::factory()->admin()->create();
        $pending = SchoolEnrollment::factory()->pending()->create();

        $this->actingAs($admin)
            ->post(route('admin.school.enrollments.reject', $pending))
            ->assertSessionHasNoErrors();

        $pending = $pending->fresh();
        $this->assertSame(SchoolEnrollmentStatus::REJECTED, $pending->status);
        $this->assertSame($admin->id, $pending->rejected_by);
        $this->assertSame(
            '2027-02-09 12:00:00',
            $pending->retention_until->format('Y-m-d H:i:s')
        );

        $active = SchoolEnrollment::factory()->active()->create();
        $this->actingAs($admin)
            ->post(route('admin.school.enrollments.withdraw', $active))
            ->assertSessionHasNoErrors();

        $active = $active->fresh();
        $this->assertSame($admin->id, $active->withdrawn_by);
        $this->assertSame(
            '2028-08-09 12:00:00',
            $active->retention_until->format('Y-m-d H:i:s')
        );
    }

    public function test_retention_hold_dry_run_and_purge_are_safe_and_idempotent(): void
    {
        $admin = User::factory()->admin()->create();
        $enrollment = SchoolEnrollment::factory()->rejected()->create([
            'user_id' => User::factory(),
            'participant_name' => 'Dato que debe desaparecer',
            'retention_until' => CarbonImmutable::now()->subDay(),
        ]);

        $this->actingAs($admin)
            ->post(route('admin.school.enrollments.retention-hold', $enrollment), [
                'retention_hold_reason' => 'Reclamación en revisión',
            ])
            ->assertSessionHasNoErrors();

        $this->artisan('school:purge-expired', ['--dry-run' => true])
            ->expectsOutput('Inscripciones elegibles: 0. Sin cambios.')
            ->assertSuccessful();

        $this->actingAs($admin)
            ->post(route('admin.school.enrollments.release-retention-hold', $enrollment))
            ->assertSessionHasNoErrors();

        $this->artisan('school:purge-expired', ['--dry-run' => true])
            ->expectsOutput('Inscripciones elegibles: 1. Sin cambios.')
            ->assertSuccessful();
        $this->assertSame('Dato que debe desaparecer', $enrollment->fresh()->participant_name);

        $this->artisan('school:purge-expired')
            ->expectsOutput('Inscripciones anonimizadas: 1.')
            ->assertSuccessful();
        $this->assertNull($enrollment->fresh()->participant_name);
        $this->assertNull($enrollment->fresh()->user_id);
        $this->assertNull($enrollment->fresh()->retention_hold_reason);
        $this->assertNotNull($enrollment->fresh()->anonymized_at);

        $this->artisan('school:purge-expired')
            ->expectsOutput('Inscripciones anonimizadas: 0.')
            ->assertSuccessful();
    }

    /** @param array<string, mixed> $overrides */
    private function adultPayload(array $overrides = []): array
    {
        return array_merge([
            'participant_name' => 'Participante Adulto',
            'participant_birth_date' => '1990-01-01',
            'contact_phone' => '611 000 000',
            'contact_email' => 'adulto@example.test',
            'privacy_acknowledged' => true,
            'privacy_notice_id' => 'NOTICE-SCHOOL-ENROLLMENT',
            'privacy_notice_version' => '1.0.0',
        ], $overrides);
    }
}
