<?php

namespace Tests\Feature;

use App\Http\Middleware\RejectLegacyBearer;
use App\Models\SchoolEnrollment;
use App\Models\SchoolProgram;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Auth\Middleware\Authenticate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\InteractsWithSpaSession;
use Tests\TestCase;

class LegacyBearerGatesTest extends TestCase
{
    use InteractsWithSpaSession;
    use RefreshDatabase;

    private const SCHOOL = '/api/v1/school/enrollments';

    private const REFUSED_ISSUANCE = [
        'message' => 'La emisión de credenciales Bearer está desactivada. Usa la sesión SPA.',
        'data' => null,
    ];

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-07-28 10:15:00');
        config(['school.enrollment_enabled' => true]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    private function registerPayload(string $email = 'nuevo@example.test'): array
    {
        return [
            'name' => 'Nueva',
            'lastname' => 'Persona',
            'email' => $email,
            'password' => 'secret-pass-1',
            'password_confirmation' => 'secret-pass-1',
            'profile_declaration_accepted' => true,
            'profile_notice_id' => 'NOTICE-ACCOUNT-PROFILE',
            'profile_notice_version' => '1.0.0',
        ];
    }

    private function schoolPayload(): array
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

    private function legacyCompatibility(): void
    {
        config()->set('legacy_bearer.issuance_enabled', true);
        config()->set('legacy_bearer.acceptance_enabled', true);
    }

    private function acceptance(bool $enabled): void
    {
        config()->set('legacy_bearer.acceptance_enabled', $enabled);
    }

    private function issuance(bool $enabled): void
    {
        config()->set('legacy_bearer.issuance_enabled', $enabled);
    }

    private function schoolReady(): void
    {
        SchoolProgram::factory()->operationallyReady()->enrollmentsOpen()->create();
    }

    private function tokenState(): array
    {
        return DB::table('personal_access_tokens')->orderBy('id')->get()->map(fn ($row) => (array) $row)->all();
    }

    // --- Configuración por defecto -------------------------------------------------

    public function test_both_controls_default_to_false(): void
    {
        $this->assertFalse(config('legacy_bearer.issuance_enabled'));
        $this->assertFalse(config('legacy_bearer.acceptance_enabled'));
    }

    public function test_the_acceptance_gate_is_ordered_before_authentication_on_every_bearer_surface(): void
    {
        foreach (['GET /api/v1/me', 'POST /api/v1/auth/logout', 'GET /api/v1/admin/seasons', 'POST /api/v1/school/enrollments'] as $target) {
            [$method, $uri] = explode(' ', $target);
            $route = app('router')->getRoutes()->match(Request::create($uri, $method));
            $order = array_values(array_map(
                fn (string $name): string => explode(':', $name)[0],
                app('router')->gatherRouteMiddleware($route),
            ));

            $gate = array_search(RejectLegacyBearer::class, $order, true);
            $this->assertNotFalse($gate, "$target no lleva la puerta de aceptación.");

            $authenticate = array_search(Authenticate::class, $order, true);
            if ($authenticate !== false) {
                $this->assertLessThan($authenticate, $gate, "$target autentica antes de la puerta.");
            }
        }
    }

    // --- J4.2 Emisión --------------------------------------------------------------

    public function test_legacy_login_and_register_still_issue_tokens_while_issuance_is_enabled(): void
    {
        $this->legacyCompatibility();
        $user = User::factory()->create();

        $login = $this->postJson('/api/v1/auth/login', ['email' => $user->email, 'password' => 'password'])
            ->assertOk();
        $this->assertSame('Bearer', $login->json('data.token_type'));
        $this->assertSame(1, $user->tokens()->count());

        $register = $this->postJson('/api/v1/auth/register', $this->registerPayload())->assertCreated();
        $this->assertNotEmpty($register->json('data.token'));
        $this->assertSame(1, User::where('email', 'nuevo@example.test')->firstOrFail()->tokens()->count());
    }

    public function test_refused_login_returns_403_and_changes_no_credential_data(): void
    {
        $this->issuance(false);
        $this->acceptance(false);
        $user = User::factory()->create();
        $user->createToken('api-token');
        $tokens = $this->tokenState();
        $hash = $user->fresh()->password;

        $this->postJson('/api/v1/auth/login', ['email' => $user->email, 'password' => 'password'])
            ->assertForbidden()
            ->assertExactJson(self::REFUSED_ISSUANCE);

        $this->assertSame($tokens, $this->tokenState());
        $this->assertSame($hash, $user->fresh()->password);
        $this->assertSame(0, $this->sessionRows());
    }

    public function test_refused_login_does_not_even_validate_or_reveal_credentials(): void
    {
        $this->issuance(false);
        $this->acceptance(false);

        $this->postJson('/api/v1/auth/login', [])->assertForbidden()->assertExactJson(self::REFUSED_ISSUANCE);
        $this->postJson('/api/v1/auth/login', ['email' => 'nadie@example.test', 'password' => 'x'])
            ->assertForbidden();
    }

    public function test_refused_register_creates_no_user_and_no_token(): void
    {
        $this->issuance(false);
        $this->acceptance(false);
        $users = User::count();

        $this->postJson('/api/v1/auth/register', $this->registerPayload())
            ->assertForbidden()
            ->assertExactJson(self::REFUSED_ISSUANCE);

        $this->assertSame($users, User::count());
        $this->assertDatabaseMissing('users', ['email' => 'nuevo@example.test']);
        $this->assertSame(0, DB::table('personal_access_tokens')->count());
        $this->assertSame(0, $this->sessionRows());
    }

    public function test_session_login_register_logout_and_csrf_work_with_both_controls_off(): void
    {
        $this->issuance(false);
        $this->acceptance(false);
        $this->enableSpaSession();
        $user = User::factory()->create();

        [$csrf, $sessionId] = $this->loginSpa($user->email);
        $this->spaJson('GET', '/api/v1/me', cookies: [config('spa_session.cookie') => $sessionId])
            ->assertOk()
            ->assertJsonPath('data.user.email', $user->email);

        $this->spaJson(
            'POST',
            '/api/v1/auth/session/logout',
            cookies: [config('spa_session.cookie') => $sessionId],
            headers: ['X-CSRF-TOKEN' => $csrf],
        )->assertOk();
        $this->spaJson('GET', '/api/v1/me', cookies: [config('spa_session.cookie') => $sessionId])
            ->assertUnauthorized();

        [$registerCsrf, $anonymousId] = $this->bootstrapSpaSession();
        $this->spaJson(
            'POST',
            '/api/v1/auth/session/register',
            $this->registerPayload('session@example.test'),
            [config('spa_session.cookie') => $anonymousId],
            ['X-CSRF-TOKEN' => $registerCsrf],
        )->assertCreated();

        $this->assertSame(0, DB::table('personal_access_tokens')->count());
    }

    public function test_refused_endpoints_keep_their_throttling(): void
    {
        $this->issuance(false);
        $this->acceptance(false);

        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->postJson('/api/v1/auth/login', ['email' => 'a@example.test', 'password' => 'x'])
                ->assertForbidden();
        }
        $this->postJson('/api/v1/auth/login', ['email' => 'a@example.test', 'password' => 'x'])
            ->assertStatus(429);

        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->postJson('/api/v1/auth/register', $this->registerPayload())->assertForbidden();
        }
        $this->postJson('/api/v1/auth/register', $this->registerPayload())->assertStatus(429);
    }

    // --- J4.3 Aceptación -----------------------------------------------------------

    public function test_bearer_protected_requests_are_unchanged_while_acceptance_is_enabled(): void
    {
        $this->legacyCompatibility();
        $user = User::factory()->create();
        $token = $user->createToken('api-token')->plainTextToken;

        $this->withToken($token)->getJson('/api/v1/me')->assertOk()->assertJsonPath('data.user.email', $user->email);
    }

    public function test_rejected_bearer_returns_401_and_never_touches_the_token(): void
    {
        $this->acceptance(false);
        $user = User::factory()->create();
        $token = $user->createToken('api-token');
        $before = $this->tokenState();

        foreach (['/api/v1/me', '/api/v1/me/matches', '/api/v1/me/calendar'] as $uri) {
            $this->withToken($token->plainTextToken)->getJson($uri)
                ->assertUnauthorized()
                ->assertExactJson(['message' => 'Unauthenticated.', 'data' => null]);
        }

        $this->assertNull($token->accessToken->fresh()->last_used_at);
        $this->assertSame($before, $this->tokenState());
    }

    public function test_rejected_bearer_also_covers_the_admin_api_even_for_admins(): void
    {
        $this->acceptance(false);
        $admin = User::factory()->admin()->create();

        $this->withToken($admin->createToken('api-token')->plainTextToken)
            ->getJson('/api/v1/admin/seasons')
            ->assertUnauthorized();
    }

    public function test_bearer_scheme_detection_is_case_insensitive_and_ignores_other_schemes(): void
    {
        $this->acceptance(false);
        $user = User::factory()->create();
        $token = $user->createToken('api-token')->plainTextToken;

        $this->withHeaders(['Authorization' => 'bearer '.$token])->getJson('/api/v1/me')->assertUnauthorized();
        $this->withHeaders(['Authorization' => 'Bearer'])->getJson('/api/v1/me')->assertUnauthorized();
        // Otro esquema no es un intento Bearer: la ruta protegida lo rechaza por su cuenta (sin credencial válida).
        $this->withHeaders(['Authorization' => 'Basic dXNlcjpwYXNz'])->getJson('/api/v1/me')->assertUnauthorized();
        $this->withHeaders(['Authorization' => 'Basic dXNlcjpwYXNz'])->getJson('/api/v1/school')->assertOk();
    }

    public function test_public_routes_ignore_authorization_headers_even_when_acceptance_is_off(): void
    {
        $this->acceptance(false);

        $this->withToken('cualquiera')->getJson('/api/v1/school')->assertOk();
    }

    public function test_spa_session_requests_are_unaffected_while_acceptance_is_off(): void
    {
        $this->acceptance(false);
        $this->issuance(false);
        $this->enableSpaSession();
        $user = User::factory()->create();
        [, $sessionId] = $this->loginSpa($user->email);

        $this->spaJson('GET', '/api/v1/me', cookies: [config('spa_session.cookie') => $sessionId])
            ->assertOk()
            ->assertJsonPath('data.user.email', $user->email);
    }

    public function test_bearer_with_a_session_cookie_is_still_rejected_and_never_falls_back_to_the_session(): void
    {
        $this->acceptance(false);
        $this->enableSpaSession();
        $user = User::factory()->create();
        [, $sessionId] = $this->loginSpa($user->email);

        $this->spaJson(
            'GET',
            '/api/v1/me',
            cookies: [config('spa_session.cookie') => $sessionId],
            headers: ['Authorization' => 'Bearer inventado'],
        )->assertUnauthorized();
    }

    public function test_legacy_logout_follows_the_acceptance_gate_and_session_logout_does_not(): void
    {
        $this->legacyCompatibility();
        $user = User::factory()->create();
        $token = $user->createToken('api-token');

        $this->withToken($token->plainTextToken)->postJson('/api/v1/auth/logout')->assertOk();
        $this->assertDatabaseMissing('personal_access_tokens', ['id' => $token->accessToken->id]);

        $second = $user->createToken('api-token');
        $this->acceptance(false);
        $this->withToken($second->plainTextToken)->postJson('/api/v1/auth/logout')->assertUnauthorized();
        $this->assertDatabaseHas('personal_access_tokens', ['id' => $second->accessToken->id]);

        $this->enableSpaSession();
        [$csrf, $sessionId] = $this->loginSpa($user->email);
        $this->spaJson(
            'POST',
            '/api/v1/auth/session/logout',
            cookies: [config('spa_session.cookie') => $sessionId],
            headers: ['X-CSRF-TOKEN' => $csrf],
        )->assertOk();
    }

    public function test_the_gate_runs_before_authentication_so_an_invalid_token_gets_the_same_401(): void
    {
        $this->acceptance(false);

        $this->withToken('999|no-existe')->getJson('/api/v1/me')
            ->assertUnauthorized()
            ->assertExactJson(['message' => 'Unauthenticated.', 'data' => null]);
    }

    // --- Escuela -------------------------------------------------------------------

    public function test_school_without_authorization_stays_anonymous_while_acceptance_is_off(): void
    {
        $this->schoolReady();
        $this->acceptance(false);

        $this->postJson(self::SCHOOL, $this->schoolPayload())->assertCreated();

        $this->assertDatabaseHas('school_enrollments', ['user_id' => null]);
    }

    public function test_school_with_bearer_fails_closed_when_acceptance_is_off(): void
    {
        $this->schoolReady();
        $this->acceptance(false);
        $user = User::factory()->create();
        $token = $user->createToken('school-enrollment');

        foreach ([$token->plainTextToken, 'token-invalido'] as $bearer) {
            $this->withToken($bearer)->postJson(self::SCHOOL, $this->schoolPayload())
                ->assertUnauthorized()
                ->assertExactJson(['message' => 'Unauthenticated.', 'data' => null]);
        }

        $this->assertSame(0, SchoolEnrollment::count());
        $this->assertNull($token->accessToken->fresh()->last_used_at);
    }

    public function test_school_with_a_spa_session_still_associates_the_user_while_acceptance_is_off(): void
    {
        $this->schoolReady();
        $this->acceptance(false);
        $this->issuance(false);
        $this->enableSpaSession();
        $user = User::factory()->create();
        [$csrf, $sessionId] = $this->loginSpa($user->email);

        $this->spaJson(
            'POST',
            self::SCHOOL,
            $this->schoolPayload(),
            [config('spa_session.cookie') => $sessionId],
            ['X-CSRF-TOKEN' => $csrf],
        )->assertCreated();

        $this->assertSame($user->id, SchoolEnrollment::query()->sole()->user_id);
    }

    public function test_school_bearer_association_is_preserved_while_acceptance_is_enabled(): void
    {
        $this->legacyCompatibility();
        $this->schoolReady();
        $user = User::factory()->create();

        $this->withToken($user->createToken('school-enrollment')->plainTextToken)
            ->postJson(self::SCHOOL, $this->schoolPayload())
            ->assertCreated();

        $this->assertSame($user->id, SchoolEnrollment::query()->sole()->user_id);
    }
}
