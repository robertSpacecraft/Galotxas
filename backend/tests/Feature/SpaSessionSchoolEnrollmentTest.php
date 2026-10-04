<?php

namespace Tests\Feature;

use App\Models\SchoolEnrollment;
use App\Models\SchoolProgram;
use App\Models\User;
use App\Services\UserAuthenticationRevocationService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\EnablesLegacyBearerCompatibility;
use Tests\Concerns\InteractsWithSpaSession;
use Tests\TestCase;

class SpaSessionSchoolEnrollmentTest extends TestCase
{
    use EnablesLegacyBearerCompatibility;
    use InteractsWithSpaSession;
    use RefreshDatabase;

    private const URI = '/api/v1/school/enrollments';

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-07-28 10:15:00');
        config(['school.enrollment_enabled' => true]);
        SchoolProgram::factory()->operationallyReady()->enrollmentsOpen()->create();
        $this->enableSpaSession();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    private function payload(): array
    {
        return [
            'participant_name' => 'Participante Adulto',
            'participant_birth_date' => '1990-01-01',
            'contact_phone' => '611 000 000',
            'contact_email' => 'adulto@example.test',
            'guardian_name' => '',
            'guardian_relationship' => '',
            'privacy_acknowledged' => true,
            'privacy_notice_id' => 'NOTICE-SCHOOL-ENROLLMENT',
            'privacy_notice_version' => '1.0.0',
        ];
    }

    public function test_anonymous_enrollment_stays_stateless_and_anonymous(): void
    {
        $this->spaJson('POST', self::URI, $this->payload(), sessionMode: false)
            ->assertCreated()
            ->assertCookieMissing((string) config('spa_session.cookie'));

        $this->assertDatabaseHas('school_enrollments', ['user_id' => null]);
        $this->assertSame(0, $this->sessionRows());
    }

    public function test_authenticated_spa_session_enrollment_is_linked_to_the_current_user(): void
    {
        $user = User::factory()->create();
        [$csrf, $sessionId] = $this->loginSpa($user->email);

        $this->spaJson(
            'POST',
            self::URI,
            $this->payload(),
            [config('spa_session.cookie') => $sessionId],
            ['X-CSRF-TOKEN' => $csrf],
        )->assertCreated();

        $this->assertSame($user->id, SchoolEnrollment::query()->sole()->user_id);
    }

    public function test_claimed_session_without_cookie_or_with_anonymous_session_fails_instead_of_saving_anonymously(): void
    {
        $this->spaJson('POST', self::URI, $this->payload())->assertUnauthorized();

        [$csrf, $anonymousId] = $this->bootstrapSpaSession();
        $this->spaJson(
            'POST',
            self::URI,
            $this->payload(),
            [config('spa_session.cookie') => $anonymousId],
            ['X-CSRF-TOKEN' => $csrf],
        )->assertUnauthorized();

        $this->assertDatabaseCount('school_enrollments', 0);
    }

    public function test_revoked_session_fails_instead_of_saving_anonymously(): void
    {
        $user = User::factory()->create();
        [$csrf, $sessionId] = $this->loginSpa($user->email);
        app(UserAuthenticationRevocationService::class)->revokeAll($user);

        $this->spaJson(
            'POST',
            self::URI,
            $this->payload(),
            [config('spa_session.cookie') => $sessionId],
            ['X-CSRF-TOKEN' => $csrf],
        )->assertStatus(419);

        // El cliente refresca el CSRF (sesión anónima nueva) y el reintento sigue sin ser anónimo.
        [$freshCsrf, $freshId] = $this->bootstrapSpaSession();
        $this->spaJson(
            'POST',
            self::URI,
            $this->payload(),
            [config('spa_session.cookie') => $freshId],
            ['X-CSRF-TOKEN' => $freshCsrf],
        )->assertUnauthorized();

        $this->assertDatabaseCount('school_enrollments', 0);
    }

    public function test_session_enrollment_requires_csrf(): void
    {
        $user = User::factory()->create();
        [, $sessionId] = $this->loginSpa($user->email);

        $this->spaJson(
            'POST',
            self::URI,
            $this->payload(),
            [config('spa_session.cookie') => $sessionId],
        )->assertStatus(419);

        $this->assertDatabaseCount('school_enrollments', 0);
    }

    public function test_foreign_origin_cannot_invoke_session_mode(): void
    {
        $user = User::factory()->create();
        [$csrf, $sessionId] = $this->loginSpa($user->email);

        $this->spaJson(
            'POST',
            self::URI,
            $this->payload(),
            [config('spa_session.cookie') => $sessionId],
            ['X-CSRF-TOKEN' => $csrf, 'Origin' => 'https://evil.example'],
        )->assertUnauthorized();

        $this->assertDatabaseCount('school_enrollments', 0);
    }

    public function test_bearer_enrollment_keeps_working_with_the_flag_on(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('school-enrollment')->plainTextToken;

        $this->flushHeaders();
        $this->withToken($token)
            ->withHeaders(['Origin' => self::SPA_ORIGIN])
            ->postJson(self::URI, $this->payload())
            ->assertCreated();

        $this->assertSame($user->id, SchoolEnrollment::query()->sole()->user_id);
    }

    public function test_flag_off_keeps_anonymous_and_bearer_enrollments_working(): void
    {
        config()->set('spa_session.enabled', false);

        $this->spaJson('POST', self::URI, $this->payload(), sessionMode: false)->assertCreated();
        $this->assertDatabaseHas('school_enrollments', ['user_id' => null]);

        $user = User::factory()->create();
        $this->flushHeaders();
        $this->withToken($user->createToken('school-enrollment')->plainTextToken)
            ->postJson(self::URI, $this->payload())
            ->assertCreated();

        $this->assertSame(2, SchoolEnrollment::query()->count());
        $this->assertDatabaseHas('school_enrollments', ['user_id' => $user->id]);
    }

    public function test_session_claim_with_the_flag_off_fails_closed_instead_of_saving_anonymously(): void
    {
        config()->set('spa_session.enabled', false);

        $this->spaJson('POST', self::URI, $this->payload())
            ->assertForbidden()
            ->assertExactJson([
                'message' => 'La sesión SPA no está disponible para esta petición.',
                'data' => null,
            ]);

        $this->assertDatabaseCount('school_enrollments', 0);
        $this->assertSame(0, $this->sessionRows());
    }
}
