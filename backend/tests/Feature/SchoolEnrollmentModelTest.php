<?php

namespace Tests\Feature;

use App\Enums\SchoolEnrollmentStatus;
use App\Models\SchoolEnrollment;
use App\Models\SchoolLevel;
use App\Models\SchoolProgram;
use App\Models\User;
use App\Services\SchoolEnrollmentAgeService;
use App\Services\SchoolEnrollmentService;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
use Tests\TestCase;

class SchoolEnrollmentModelTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_table_columns_defaults_casts_and_relations_are_available(): void
    {
        $this->assertTrue(Schema::hasColumns('school_enrollments', [
            'id',
            'school_program_id',
            'school_level_id',
            'user_id',
            'participant_name',
            'participant_birth_date',
            'contact_phone',
            'contact_email',
            'guardian_name',
            'guardian_relationship',
            'status',
            'requested_at',
            'activated_at',
            'rejected_at',
            'withdrawn_at',
            'admin_notes',
            'created_at',
            'updated_at',
        ]));

        $program = SchoolProgram::factory()->create();
        $user = User::factory()->create();
        $enrollment = new SchoolEnrollment;
        $enrollment->forceFill([
            'school_program_id' => $program->id,
            'user_id' => $user->id,
            'participant_name' => 'Participante',
            'participant_birth_date' => '2000-01-01',
            'contact_phone' => '600 000 000',
            'contact_email' => 'contacto@example.test',
            'requested_at' => '2026-07-28 10:00:00',
        ])->save();
        $enrollment = $enrollment->fresh();

        $this->assertSame(SchoolEnrollmentStatus::PENDING, $enrollment->status);
        $this->assertInstanceOf(CarbonImmutable::class, $enrollment->participant_birth_date);
        $this->assertInstanceOf(CarbonImmutable::class, $enrollment->requested_at);
        $this->assertNull($enrollment->school_level_id);
        $this->assertNull($enrollment->activated_at);
        $this->assertTrue($enrollment->program->is($program));
        $this->assertTrue($enrollment->user->is($user));
        $this->assertTrue($program->enrollments->contains($enrollment));
        $this->assertTrue($user->schoolEnrollments->contains($enrollment));
    }

    public function test_factory_states_generate_valid_combinations(): void
    {
        $pending = SchoolEnrollment::factory()->pending()->minor()->create();
        $active = SchoolEnrollment::factory()->active()->adult()->linkedToUser()->create();
        $rejected = SchoolEnrollment::factory()->rejected()->create();
        $withdrawn = SchoolEnrollment::factory()->withdrawn()->create();

        $this->assertSame(SchoolEnrollmentStatus::PENDING, $pending->status);
        $this->assertTrue($pending->wasMinorAtRequest());
        $this->assertNotNull($pending->guardian_name);
        $this->assertSame(SchoolEnrollmentStatus::ACTIVE, $active->status);
        $this->assertNotNull($active->school_level_id);
        $this->assertNotNull($active->user_id);
        $this->assertNotNull($active->activated_at);
        $this->assertSame(SchoolEnrollmentStatus::REJECTED, $rejected->status);
        $this->assertNotNull($rejected->rejected_at);
        $this->assertSame(SchoolEnrollmentStatus::WITHDRAWN, $withdrawn->status);
        $this->assertNotNull($withdrawn->activated_at);
        $this->assertNotNull($withdrawn->withdrawn_at);
    }

    public function test_user_deletion_preserves_enrollment_and_nulls_optional_link(): void
    {
        $user = User::factory()->create();
        $enrollment = SchoolEnrollment::factory()->linkedToUser($user)->create();

        $user->delete();

        $this->assertDatabaseHas('school_enrollments', [
            'id' => $enrollment->id,
            'user_id' => null,
        ]);
    }

    public function test_program_and_level_deletion_are_restricted_when_enrollments_exist(): void
    {
        $program = SchoolProgram::factory()->create();
        $level = SchoolLevel::factory()
            ->for($program, 'program')
            ->active()
            ->create();
        SchoolEnrollment::factory()
            ->for($program, 'program')
            ->assignedToLevel($level)
            ->create();

        try {
            $level->delete();
            $this->fail('El nivel referenciado no debería poder eliminarse.');
        } catch (QueryException) {
            $this->assertDatabaseHas('school_levels', ['id' => $level->id]);
        }

        try {
            $program->delete();
            $this->fail('El programa referenciado no debería poder eliminarse.');
        } catch (QueryException) {
            $this->assertDatabaseHas('school_programs', ['id' => $program->id]);
        }
    }

    public function test_database_rejects_a_level_from_another_program(): void
    {
        $program = SchoolProgram::factory()->create();
        $otherProgram = SchoolProgram::factory()->create();
        $otherLevel = SchoolLevel::factory()
            ->for($otherProgram, 'program')
            ->active()
            ->create();

        $this->expectException(QueryException::class);

        DB::table('school_enrollments')->insert([
            'school_program_id' => $program->id,
            'school_level_id' => $otherLevel->id,
            'participant_name' => 'Participante',
            'participant_birth_date' => '2000-01-01',
            'contact_phone' => '600 000 000',
            'contact_email' => 'contacto@example.test',
            'requested_at' => '2026-07-28 10:00:00',
            'created_at' => '2026-07-28 10:00:00',
            'updated_at' => '2026-07-28 10:00:00',
        ]);
    }

    public function test_status_scopes_and_stable_order_are_available(): void
    {
        $older = SchoolEnrollment::factory()->pending()->create([
            'requested_at' => '2026-01-01 10:00:00',
        ]);
        $newerFirst = SchoolEnrollment::factory()->active()->create([
            'requested_at' => '2026-02-01 10:00:00',
        ]);
        $newerSecond = SchoolEnrollment::factory()->rejected()->create([
            'requested_at' => '2026-02-01 10:00:00',
        ]);
        $withdrawn = SchoolEnrollment::factory()->withdrawn()->create();

        $this->assertTrue(SchoolEnrollment::query()->pending()->sole()->is($older));
        $this->assertTrue(SchoolEnrollment::query()->active()->sole()->is($newerFirst));
        $this->assertTrue(SchoolEnrollment::query()->rejected()->sole()->is($newerSecond));
        $this->assertTrue(SchoolEnrollment::query()->withdrawn()->sole()->is($withdrawn));

        $orderedIds = SchoolEnrollment::query()
            ->whereKey([$older->id, $newerFirst->id, $newerSecond->id])
            ->ordered()
            ->pluck('id')
            ->all();

        $this->assertSame([$newerSecond->id, $newerFirst->id, $older->id], $orderedIds);
    }

    public function test_age_is_deterministic_at_request_and_handles_birthdays(): void
    {
        $reference = CarbonImmutable::parse('2026-07-28 17:30:00');

        $this->assertFalse(SchoolEnrollmentAgeService::isMinor(
            CarbonImmutable::parse('2008-07-28'),
            $reference
        ));
        $this->assertTrue(SchoolEnrollmentAgeService::isMinor(
            CarbonImmutable::parse('2008-07-29'),
            $reference
        ));
        $this->assertFalse(SchoolEnrollmentAgeService::isMinor(
            CarbonImmutable::parse('1980-01-01'),
            $reference
        ));

        $historical = SchoolEnrollment::factory()->create([
            'participant_birth_date' => '2010-08-01',
            'requested_at' => '2026-07-28 17:30:00',
        ]);
        Carbon::setTestNow('2040-01-01 00:00:00');

        $this->assertTrue($historical->wasMinorAtRequest());
    }

    public function test_age_service_rejects_future_birth_and_handles_leap_day(): void
    {
        $this->assertFalse(SchoolEnrollmentAgeService::isMinor(
            CarbonImmutable::parse('2008-02-29'),
            CarbonImmutable::parse('2026-02-28')
        ));

        $this->expectException(InvalidArgumentException::class);

        SchoolEnrollmentAgeService::isMinor(
            CarbonImmutable::parse('2026-07-29'),
            CarbonImmutable::parse('2026-07-28')
        );
    }

    public function test_service_applies_valid_transitions_and_cycle_dates(): void
    {
        Carbon::setTestNow('2026-07-28 10:00:00');
        $program = SchoolProgram::factory()->create();
        $level = SchoolLevel::factory()
            ->for($program, 'program')
            ->active()
            ->create();
        $enrollment = SchoolEnrollment::factory()
            ->for($program, 'program')
            ->pending()
            ->create([
                'requested_at' => '2026-07-20 09:00:00',
            ]);
        $service = app(SchoolEnrollmentService::class);

        $active = $service->approve($enrollment, $level->id);

        $this->assertSame(SchoolEnrollmentStatus::ACTIVE, $active->status);
        $this->assertTrue($active->level->is($level));
        $this->assertSame('2026-07-20 09:00:00', $active->requested_at->format('Y-m-d H:i:s'));
        $this->assertSame('2026-07-28 10:00:00', $active->activated_at->format('Y-m-d H:i:s'));
        $this->assertNull($active->rejected_at);
        $this->assertNull($active->withdrawn_at);

        Carbon::setTestNow('2026-08-15 12:00:00');
        $withdrawn = $service->withdraw($active);

        $this->assertSame(SchoolEnrollmentStatus::WITHDRAWN, $withdrawn->status);
        $this->assertSame('2026-07-28 10:00:00', $withdrawn->activated_at->format('Y-m-d H:i:s'));
        $this->assertSame('2026-08-15 12:00:00', $withdrawn->withdrawn_at->format('Y-m-d H:i:s'));

        Carbon::setTestNow('2026-07-28 11:00:00');
        $rejected = $service->reject(
            SchoolEnrollment::factory()->for($program, 'program')->pending()->create()
        );

        $this->assertSame(SchoolEnrollmentStatus::REJECTED, $rejected->status);
        $this->assertSame('2026-07-28 11:00:00', $rejected->rejected_at->format('Y-m-d H:i:s'));
        $this->assertNull($rejected->activated_at);
        $this->assertNull($rejected->withdrawn_at);
    }

    public function test_service_reassigns_only_active_enrollments_with_active_level_in_same_program(): void
    {
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
            ->create();
        $originalActivatedAt = $enrollment->activated_at;
        $service = app(SchoolEnrollmentService::class);

        $reassigned = $service->reassignLevel($enrollment, $secondLevel->id);

        $this->assertTrue($reassigned->level->is($secondLevel));
        $this->assertSame(SchoolEnrollmentStatus::ACTIVE, $reassigned->status);
        $this->assertTrue($reassigned->activated_at->equalTo($originalActivatedAt));
        $this->assertNull($reassigned->withdrawn_at);
    }

    public function test_service_rejects_invalid_transitions_and_never_reactivates_history(): void
    {
        $service = app(SchoolEnrollmentService::class);
        $level = SchoolLevel::factory()->active()->create();
        $pending = SchoolEnrollment::factory()
            ->for($level->program, 'program')
            ->pending()
            ->create();
        $rejected = SchoolEnrollment::factory()
            ->for($level->program, 'program')
            ->rejected()
            ->create();
        $withdrawn = SchoolEnrollment::factory()
            ->withdrawn()
            ->create();

        $invalidActions = [
            fn () => $service->withdraw($pending),
            fn () => $service->approve($rejected, $level->id),
            fn () => $service->reject($rejected),
            fn () => $service->approve($withdrawn, $withdrawn->school_level_id),
            fn () => $service->withdraw($withdrawn),
            fn () => $service->reassignLevel($rejected, $level->id),
        ];

        foreach ($invalidActions as $action) {
            try {
                $action();
                $this->fail('La transición inválida debería haber sido rechazada.');
            } catch (ValidationException $exception) {
                $this->assertArrayHasKey('status', $exception->errors());
            }
        }

        $this->assertSame(SchoolEnrollmentStatus::PENDING, $pending->fresh()->status);
        $this->assertSame(SchoolEnrollmentStatus::REJECTED, $rejected->fresh()->status);
        $this->assertSame(SchoolEnrollmentStatus::WITHDRAWN, $withdrawn->fresh()->status);
    }

    public function test_service_rejects_missing_inactive_and_cross_program_levels(): void
    {
        $program = SchoolProgram::factory()->create();
        $pending = SchoolEnrollment::factory()
            ->for($program, 'program')
            ->pending()
            ->create();
        $inactive = SchoolLevel::factory()
            ->for($program, 'program')
            ->inactive()
            ->create();
        $other = SchoolLevel::factory()->active()->create();
        $service = app(SchoolEnrollmentService::class);

        foreach ([null, $inactive->id, $other->id] as $levelId) {
            try {
                $service->approve($pending, (int) $levelId);
                $this->fail('El nivel inválido debería haber sido rechazado.');
            } catch (ValidationException $exception) {
                $this->assertArrayHasKey('school_level_id', $exception->errors());
            }
        }

        $this->assertSame(SchoolEnrollmentStatus::PENDING, $pending->fresh()->status);
        $this->assertNull($pending->fresh()->school_level_id);
    }
}
