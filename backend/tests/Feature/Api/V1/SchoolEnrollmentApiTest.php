<?php

namespace Tests\Feature\Api\V1;

use App\Enums\SchoolEnrollmentStatus;
use App\Models\SchoolLevel;
use App\Models\SchoolProgram;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\Fluent\AssertableJson;
use Tests\TestCase;

class SchoolEnrollmentApiTest extends TestCase
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

    public function test_anonymous_minor_can_submit_to_public_open_program_without_level(): void
    {
        $program = SchoolProgram::factory()
            ->publiclyVisible()
            ->enrollmentsOpen()
            ->create();
        $payload = $this->minorPayload();

        $response = $this->postJson('/api/v1/school/enrollments', $payload);

        $response
            ->assertCreated()
            ->assertJson(fn (AssertableJson $json) => $json
                ->where(
                    'message',
                    'La solicitud de inscripción se ha recibido correctamente.'
                )
                ->where('data', null)
                ->missing('id')
                ->missing('status')
                ->missing('participant_name')
                ->missing('contact_email')
            );

        $this->assertDatabaseHas('school_enrollments', [
            'school_program_id' => $program->id,
            'school_level_id' => null,
            'user_id' => null,
            'participant_name' => 'Alumna Menor',
            'participant_birth_date' => '2012-08-01',
            'contact_phone' => '600 123 123',
            'contact_email' => 'familia@example.test',
            'guardian_name' => 'Persona Tutora',
            'guardian_relationship' => 'Madre',
            'status' => SchoolEnrollmentStatus::PENDING->value,
            'requested_at' => '2026-07-28 10:15:00',
            'activated_at' => null,
            'rejected_at' => null,
            'withdrawn_at' => null,
            'admin_notes' => null,
        ]);
    }

    public function test_authenticated_request_links_only_session_user_without_overwriting_payload(): void
    {
        SchoolProgram::factory()
            ->publiclyVisible()
            ->enrollmentsOpen()
            ->create();
        $user = User::factory()->create([
            'name' => 'Nombre de cuenta',
            'email' => 'cuenta@example.test',
        ]);
        $token = $user->createToken('school-enrollment')->plainTextToken;

        $this->withToken($token)
            ->postJson('/api/v1/school/enrollments', $this->adultPayload([
                'participant_name' => 'Nombre facilitado',
                'contact_email' => 'CONTACTO@EXAMPLE.TEST',
            ]))
            ->assertCreated();

        $this->assertDatabaseHas('school_enrollments', [
            'user_id' => $user->id,
            'participant_name' => 'Nombre facilitado',
            'contact_email' => 'contacto@example.test',
        ]);
        $this->assertDatabaseMissing('players', ['user_id' => $user->id]);
    }

    public function test_adult_guardian_fields_are_normalized_to_null_and_contact_remains_required(): void
    {
        SchoolProgram::factory()
            ->publiclyVisible()
            ->enrollmentsOpen()
            ->create();

        $this->postJson('/api/v1/school/enrollments', $this->adultPayload([
            'guardian_name' => 'No debe persistir',
            'guardian_relationship' => 'No procede',
        ]))->assertCreated();

        $this->assertDatabaseHas('school_enrollments', [
            'participant_name' => 'Participante Adulto',
            'guardian_name' => null,
            'guardian_relationship' => null,
            'contact_phone' => '611 000 000',
            'contact_email' => 'adulto@example.test',
        ]);
    }

    public function test_minor_requires_both_guardian_fields(): void
    {
        SchoolProgram::factory()
            ->publiclyVisible()
            ->enrollmentsOpen()
            ->create();

        $this->postJson('/api/v1/school/enrollments', $this->minorPayload([
            'guardian_name' => '',
            'guardian_relationship' => '',
        ]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'guardian_name',
                'guardian_relationship',
            ]);

        $this->assertDatabaseCount('school_enrollments', 0);
    }

    public function test_public_validation_requires_contact_and_valid_non_future_birth(): void
    {
        SchoolProgram::factory()
            ->publiclyVisible()
            ->enrollmentsOpen()
            ->create();

        $this->postJson('/api/v1/school/enrollments', [
            'participant_name' => '',
            'participant_birth_date' => '2026-07-29',
            'contact_phone' => '',
            'contact_email' => 'correo-invalido',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'participant_name',
                'participant_birth_date',
                'contact_phone',
                'contact_email',
            ]);

        $this->assertDatabaseCount('school_enrollments', 0);
    }

    public function test_optional_public_level_must_be_public_active_and_in_current_program(): void
    {
        $program = SchoolProgram::factory()
            ->publiclyVisible()
            ->enrollmentsOpen()
            ->create();
        $validLevel = SchoolLevel::factory()
            ->for($program, 'program')
            ->active()
            ->publiclyVisible()
            ->create();
        $privateLevel = SchoolLevel::factory()
            ->for($program, 'program')
            ->active()
            ->privatelyVisible()
            ->create();
        $inactiveLevel = SchoolLevel::factory()
            ->for($program, 'program')
            ->inactive()
            ->publiclyVisible()
            ->create();
        $otherLevel = SchoolLevel::factory()
            ->active()
            ->publiclyVisible()
            ->create();

        $this->postJson('/api/v1/school/enrollments', $this->adultPayload([
            'school_level_id' => $validLevel->id,
            'contact_email' => 'valid@example.test',
        ]))->assertCreated();

        foreach ([
            $privateLevel->id,
            $inactiveLevel->id,
            $otherLevel->id,
            999999,
        ] as $index => $levelId) {
            $this->postJson('/api/v1/school/enrollments', $this->adultPayload([
                'school_level_id' => $levelId,
                'contact_email' => "invalid-{$index}@example.test",
            ]))
                ->assertUnprocessable()
                ->assertJsonValidationErrors('school_level_id');
        }

        $this->assertDatabaseHas('school_enrollments', [
            'school_level_id' => $validLevel->id,
        ]);
        $this->assertDatabaseCount('school_enrollments', 1);
    }

    public function test_missing_private_or_closed_program_returns_same_generic_conflict(): void
    {
        $program = SchoolProgram::factory()
            ->privatelyVisible()
            ->enrollmentsOpen()
            ->create();

        $this->assertUnavailable($this->adultPayload([
            'contact_email' => 'private@example.test',
        ]));

        $program->update([
            'is_public' => true,
            'enrollments_open' => false,
        ]);

        $this->assertUnavailable($this->adultPayload([
            'contact_email' => 'closed@example.test',
        ]));

        $program->delete();

        $this->assertUnavailable($this->adultPayload([
            'contact_email' => 'missing@example.test',
        ]));

        $this->assertDatabaseCount('school_enrollments', 0);
    }

    public function test_sensitive_and_unknown_payload_fields_are_rejected(): void
    {
        SchoolProgram::factory()
            ->publiclyVisible()
            ->enrollmentsOpen()
            ->create();
        $user = User::factory()->create();

        $this->postJson('/api/v1/school/enrollments', $this->adultPayload([
            'school_program_id' => 1,
            'user_id' => $user->id,
            'status' => 'active',
            'requested_at' => '2000-01-01 00:00:00',
            'activated_at' => '2000-01-01 00:00:00',
            'rejected_at' => '2000-01-01 00:00:00',
            'withdrawn_at' => '2000-01-01 00:00:00',
            'admin_notes' => 'Dato privado',
            'unexpected' => 'campo extra',
        ]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'school_program_id',
                'user_id',
                'status',
                'requested_at',
                'activated_at',
                'rejected_at',
                'withdrawn_at',
                'admin_notes',
                'payload',
            ]);

        $this->assertDatabaseCount('school_enrollments', 0);
    }

    public function test_public_response_never_contains_personal_or_internal_data(): void
    {
        SchoolProgram::factory()
            ->publiclyVisible()
            ->enrollmentsOpen()
            ->create();
        $payload = $this->adultPayload([
            'participant_name' => 'Dato especialmente privado',
            'contact_email' => 'privado@example.test',
            'contact_phone' => '699 999 999',
        ]);

        $response = $this->postJson('/api/v1/school/enrollments', $payload)
            ->assertCreated();

        $content = $response->getContent();

        $this->assertStringNotContainsString('Dato especialmente privado', $content);
        $this->assertStringNotContainsString('privado@example.test', $content);
        $this->assertStringNotContainsString('699 999 999', $content);
        $this->assertStringNotContainsString('pending', $content);
        $this->assertStringNotContainsString('"id"', $content);
        $this->assertStringNotContainsString('admin_notes', $content);
    }

    public function test_school_enrollment_rate_limiter_allows_five_and_blocks_sixth(): void
    {
        SchoolProgram::factory()
            ->publiclyVisible()
            ->enrollmentsOpen()
            ->create();
        $payload = $this->adultPayload([
            'contact_email' => 'rate-limit@example.test',
        ]);

        for ($attempt = 1; $attempt <= 5; $attempt++) {
            $this->postJson('/api/v1/school/enrollments', $payload)
                ->assertCreated();
        }

        $this->postJson('/api/v1/school/enrollments', $this->adultPayload([
            'contact_email' => 'RATE-LIMIT@example.test',
        ]))
            ->assertTooManyRequests()
            ->assertExactJson([
                'message' => 'Demasiadas solicitudes. Inténtalo de nuevo más tarde.',
                'data' => null,
            ]);

        $this->getJson('/api/v1/seasons')->assertOk();
        $this->assertDatabaseCount('school_enrollments', 5);
    }

    public function test_no_public_read_or_tracking_endpoint_exists(): void
    {
        $this->getJson('/api/v1/school')->assertNotFound();
        $this->getJson('/api/v1/school/enrollments')->assertMethodNotAllowed();
        $this->getJson('/api/v1/school/enrollments/1')->assertNotFound();
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function minorPayload(array $overrides = []): array
    {
        return array_merge([
            'participant_name' => '  Alumna Menor  ',
            'participant_birth_date' => '2012-08-01',
            'contact_phone' => '  600 123 123  ',
            'contact_email' => '  FAMILIA@EXAMPLE.TEST  ',
            'guardian_name' => '  Persona Tutora  ',
            'guardian_relationship' => '  Madre  ',
        ], $overrides);
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
        ], $overrides);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function assertUnavailable(array $payload): void
    {
        $this->postJson('/api/v1/school/enrollments', $payload)
            ->assertConflict()
            ->assertExactJson([
                'message' => 'La inscripción no está disponible actualmente.',
                'data' => null,
            ]);
    }
}
