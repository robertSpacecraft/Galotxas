<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\UserAuthenticationRevocationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\InteractsWithSpaSession;
use Tests\Concerns\InteractsWithUserCredentials;
use Tests\TestCase;

class SpaSessionAuthenticationTest extends TestCase
{
    use InteractsWithSpaSession;
    use InteractsWithUserCredentials;
    use RefreshDatabase;

    private function cookieName(): string
    {
        return (string) config('spa_session.cookie');
    }

    public function test_session_mode_is_unavailable_while_the_feature_flag_is_off(): void
    {
        config()->set('app.frontend_url', self::SPA_ORIGIN);
        $user = User::factory()->create();

        $this->spaJson('GET', '/api/v1/auth/csrf')->assertForbidden()->assertJsonPath('data', null);
        $this->spaJson('POST', '/api/v1/auth/session/login', [
            'email' => $user->email,
            'password' => 'password',
        ])->assertForbidden()->assertCookieMissing($this->cookieName());

        $this->assertSame(0, $this->sessionRows());

        $legacy = $this->spaJson('POST', '/api/v1/auth/login', [
            'email' => $user->email,
            'password' => 'password',
        ], sessionMode: false)->assertOk();

        $this->assertSame(['token', 'token_type', 'user', 'player'], array_keys($legacy->json('data')));
        $this->assertSame(1, $user->tokens()->count());
    }

    public function test_only_the_explicit_header_from_the_exact_first_party_origin_enters_session_mode(): void
    {
        $this->enableSpaSession();

        $this->spaJson('GET', '/api/v1/auth/csrf', sessionMode: false)->assertForbidden();
        $this->spaJson('GET', '/api/v1/auth/csrf', headers: ['Origin' => 'https://evil.example'])->assertForbidden();
        $this->spaJson('GET', '/api/v1/auth/csrf', headers: ['Origin' => 'https://staging.galotxesmonover.es'])->assertForbidden();
        $this->spaJson('GET', '/api/v1/auth/csrf', headers: ['Origin' => 'https://galotxesmonover.es.evil.example'])->assertForbidden();
        $this->spaJson('GET', '/api/v1/auth/csrf', headers: ['X-Galotxas-Auth-Mode' => 'cookie'])->assertForbidden();
        $this->flushHeaders();
        $this->withHeaders(['X-Galotxas-Auth-Mode' => 'session'])->getJson('/api/v1/auth/csrf')->assertForbidden();

        $this->assertSame(0, $this->sessionRows());
        $this->spaJson('GET', '/api/v1/auth/csrf')->assertOk();
    }

    public function test_bearer_requests_stay_bearer_even_from_the_first_party_origin_with_the_mode_header(): void
    {
        $this->enableSpaSession();
        $user = User::factory()->create();
        $current = $user->createToken('api-token');
        $other = $user->createToken('api-token');

        $this->spaJson('POST', '/api/v1/auth/logout', headers: [
            'Authorization' => 'Bearer '.$current->plainTextToken,
        ])->assertOk()->assertJsonPath('message', 'Logout correcto.');

        $this->assertDatabaseMissing('personal_access_tokens', ['id' => $current->accessToken->id]);
        $this->assertDatabaseHas('personal_access_tokens', ['id' => $other->accessToken->id]);

        $this->spaJson('GET', '/api/v1/auth/csrf', headers: [
            'Authorization' => 'Bearer '.$other->plainTextToken,
        ])->assertForbidden();

        $this->app['auth']->forgetGuards();
        $this->spaJson('POST', '/api/v1/auth/logout', headers: [
            'Authorization' => 'Bearer '.$other->plainTextToken,
        ], sessionMode: false)->assertOk();
        $this->assertSame(0, $this->sessionRows());
    }

    public function test_csrf_bootstrap_sets_only_the_host_only_spa_cookie_and_leaks_no_credentials(): void
    {
        $this->enableSpaSession();

        $response = $this->spaJson('GET', '/api/v1/auth/csrf')->assertOk();
        $sessionId = $this->spaCookie($response);
        $raw = $response->getCookie($this->cookieName(), false);

        $this->assertIsString($response->json('data.csrf_token'));
        $this->assertSame(['csrf_token'], array_keys($response->json('data')));
        $this->assertTrue($raw->isHttpOnly());
        $this->assertTrue($raw->isSecure());
        $this->assertSame('lax', $raw->getSameSite());
        $this->assertNull($raw->getDomain());
        $this->assertSame('/', $raw->getPath());
        $this->assertSame([$this->cookieName()], array_map(
            fn ($cookie) => $cookie->getName(),
            $response->baseResponse->headers->getCookies()
        ));
        $this->assertStringNotContainsString($sessionId, $response->getContent());
        $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
        $this->assertDatabaseHas('sessions', ['id' => $sessionId, 'user_id' => null]);
        $this->assertSame(0, DB::table('personal_access_tokens')->count());
        $this->assertSame('galotxas-session', config('session.cookie'));
    }

    public function test_session_login_authenticates_without_issuing_a_token_and_regenerates_the_session(): void
    {
        $this->enableSpaSession();
        $user = User::factory()->create();
        [$csrf, $anonymousId] = $this->bootstrapSpaSession();

        $response = $this->spaJson(
            'POST',
            '/api/v1/auth/session/login',
            ['email' => $user->email, 'password' => 'password'],
            [$this->cookieName() => $anonymousId],
            ['X-CSRF-TOKEN' => $csrf],
        )->assertOk()->assertJsonPath('message', 'Login correcto.');

        $sessionId = $this->spaCookie($response);
        $this->assertNotSame($anonymousId, $sessionId);
        $this->assertNotSame($csrf, $response->json('data.csrf_token'));
        $this->assertSame(['user', 'player', 'csrf_token'], array_keys($response->json('data')));
        $this->assertArrayNotHasKey('token', $response->json('data'));
        $this->assertArrayNotHasKey('token_type', $response->json('data'));
        $this->assertStringNotContainsString($sessionId, $response->getContent());
        $this->assertDatabaseMissing('sessions', ['id' => $anonymousId]);
        $this->assertDatabaseHas('sessions', ['id' => $sessionId, 'user_id' => $user->id]);
        $this->assertSame(0, $user->tokens()->count());

        $this->spaJson('GET', '/api/v1/me', cookies: [$this->cookieName() => $sessionId])
            ->assertOk()
            ->assertJsonPath('data.user.email', $user->email);
    }

    public function test_session_login_rejects_bad_credentials_inactive_users_and_missing_csrf(): void
    {
        $this->enableSpaSession();
        $user = User::factory()->create();
        $inactive = User::factory()->create(['active' => false]);
        [$csrf, $sessionId] = $this->bootstrapSpaSession();
        $cookies = [$this->cookieName() => $sessionId];
        $csrfHeader = ['X-CSRF-TOKEN' => $csrf];

        $this->spaJson('POST', '/api/v1/auth/session/login', ['email' => $user->email, 'password' => 'wrong'], $cookies, $csrfHeader)
            ->assertUnauthorized()->assertJsonPath('message', 'Credenciales incorrectas.');
        $this->spaJson('POST', '/api/v1/auth/session/login', ['email' => $inactive->email, 'password' => 'password'], $cookies, $csrfHeader)
            ->assertForbidden()->assertJsonPath('message', 'El usuario está inactivo.');
        $this->spaJson('POST', '/api/v1/auth/session/login', ['email' => $user->email, 'password' => 'password'], $cookies)
            ->assertStatus(419);

        $this->assertSame(0, DB::table('sessions')->whereNotNull('user_id')->count());
        $this->assertSame(0, DB::table('personal_access_tokens')->count());
    }

    public function test_session_register_authenticates_the_new_user_without_a_token_and_legacy_register_still_issues_one(): void
    {
        $this->enableSpaSession();
        $payload = fn (string $email): array => [
            'name' => 'Nueva',
            'lastname' => 'Persona',
            'email' => $email,
            'password' => 'secret-pass-1',
            'password_confirmation' => 'secret-pass-1',
            'profile_declaration_accepted' => true,
            'profile_notice_id' => 'NOTICE-ACCOUNT-PROFILE',
            'profile_notice_version' => '1.0.0',
        ];
        [$csrf, $anonymousId] = $this->bootstrapSpaSession();

        $response = $this->spaJson(
            'POST',
            '/api/v1/auth/session/register',
            $payload('session@example.test'),
            [$this->cookieName() => $anonymousId],
            ['X-CSRF-TOKEN' => $csrf],
        )->assertCreated()->assertJsonPath('message', 'Registro correcto.');

        $user = User::where('email', 'session@example.test')->firstOrFail();
        $sessionId = $this->spaCookie($response);
        $this->assertSame(['user', 'player', 'csrf_token'], array_keys($response->json('data')));
        $this->assertSame(0, $user->tokens()->count());
        $this->assertDatabaseHas('sessions', ['id' => $sessionId, 'user_id' => $user->id]);
        $this->assertDatabaseHas('profile_declarations', ['actor_user_id' => $user->id]);
        $this->spaJson('GET', '/api/v1/me', cookies: [$this->cookieName() => $sessionId])->assertOk();

        $legacy = $this->spaJson('POST', '/api/v1/auth/register', $payload('legacy@example.test'), sessionMode: false)
            ->assertCreated();
        $this->assertSame(['token', 'token_type', 'user', 'player'], array_keys($legacy->json('data')));
        $this->assertSame(1, User::where('email', 'legacy@example.test')->firstOrFail()->tokens()->count());
    }

    public function test_csrf_is_required_for_session_mutations_but_never_for_bearer_requests(): void
    {
        $this->enableSpaSession();
        $user = User::factory()->create();
        [$csrf, $sessionId] = $this->loginSpa($user->email);
        $cookies = [$this->cookieName() => $sessionId];

        $this->spaJson('POST', '/api/v1/auth/session/logout', cookies: $cookies)->assertStatus(419);
        $this->spaJson('POST', '/api/v1/auth/session/logout', cookies: $cookies, headers: ['X-CSRF-TOKEN' => 'wrong'])
            ->assertStatus(419);
        $this->spaJson('PATCH', '/api/v1/me/player-profile', cookies: $cookies)->assertStatus(419);
        $this->spaJson('GET', '/api/v1/me', cookies: $cookies)->assertOk();
        $this->assertDatabaseHas('sessions', ['id' => $sessionId, 'user_id' => $user->id]);

        $this->spaJson('PATCH', '/api/v1/me/player-profile', cookies: $cookies, headers: ['X-CSRF-TOKEN' => $csrf])
            ->assertUnprocessable();

        $token = $user->createToken('api-token')->plainTextToken;
        $this->app['auth']->forgetGuards();
        $response = $this->spaJson('PATCH', '/api/v1/me/player-profile', headers: [
            'Authorization' => 'Bearer '.$token,
        ], sessionMode: false);
        $this->assertNotSame(419, $response->getStatusCode());
        $response->assertUnprocessable();
    }

    public function test_session_logout_only_closes_the_current_spa_session(): void
    {
        $this->enableSpaSession();
        $user = User::factory()->create();
        [$csrfA, $sessionA] = $this->loginSpa($user->email);
        [, $sessionB] = $this->loginSpa($user->email);
        $bladeSession = $this->createDurableSession($user);
        $token = $user->createToken('api-token');

        $response = $this->spaJson(
            'POST',
            '/api/v1/auth/session/logout',
            cookies: [$this->cookieName() => $sessionA],
            headers: ['X-CSRF-TOKEN' => $csrfA],
        )->assertOk()->assertJsonPath('message', 'Logout correcto.');

        $this->assertNotSame($csrfA, $response->json('data.csrf_token'));
        $this->assertDatabaseMissing('sessions', ['id' => $sessionA]);
        $this->assertDatabaseHas('sessions', ['id' => $sessionB, 'user_id' => $user->id]);
        $this->assertDatabaseHas('sessions', ['id' => $bladeSession, 'user_id' => $user->id]);
        $this->assertDatabaseHas('personal_access_tokens', ['id' => $token->accessToken->id]);

        $this->spaJson('GET', '/api/v1/me', cookies: [$this->cookieName() => $sessionA])->assertUnauthorized();
        $this->spaJson('GET', '/api/v1/me', cookies: [$this->cookieName() => $sessionB])->assertOk();
    }

    public function test_legacy_logout_cannot_be_used_by_a_session_and_leaves_it_intact(): void
    {
        $this->enableSpaSession();
        $user = User::factory()->create();
        $token = $user->createToken('api-token');
        [$csrf, $sessionId] = $this->loginSpa($user->email);

        $this->spaJson('POST', '/api/v1/auth/logout', cookies: [$this->cookieName() => $sessionId], headers: ['X-CSRF-TOKEN' => $csrf])
            ->assertStatus(409);

        $this->assertDatabaseHas('sessions', ['id' => $sessionId, 'user_id' => $user->id]);
        $this->assertDatabaseHas('personal_access_tokens', ['id' => $token->accessToken->id]);
    }

    public function test_spa_and_blade_sessions_are_isolated_in_both_directions(): void
    {
        $this->enableSpaSession();
        $admin = User::factory()->admin()->create();
        $bladeName = config('session.cookie');
        [$csrf, $spaSession] = $this->loginSpa($admin->email);

        $this->flushHeaders();
        $this->defaultCookies = [];
        $this->withCookie($this->cookieName(), $spaSession)
            ->get(route('admin.dashboard'))
            ->assertRedirect(route('admin.login'));

        $login = $this->post(route('admin.login.submit'), ['email' => $admin->email, 'password' => 'password'])
            ->assertRedirect(route('admin.dashboard'));
        $bladeSession = $login->getCookie($bladeName)->getValue();
        $this->assertNotSame($spaSession, $bladeSession);
        $this->assertSame($bladeName, config('session.cookie'));

        $this->spaJson('GET', '/api/v1/me', cookies: [$bladeName => $bladeSession])->assertUnauthorized();
        $this->spaJson('GET', '/api/v1/admin/seasons', cookies: [$bladeName => $bladeSession])->assertUnauthorized();

        $this->spaJson('POST', '/api/v1/auth/session/logout', cookies: [$this->cookieName() => $spaSession], headers: ['X-CSRF-TOKEN' => $csrf])
            ->assertOk();
        $this->flushHeaders();
        $this->defaultCookies = [];
        $this->app['auth']->forgetGuards();
        $this->withCookie($bladeName, $bladeSession)->get(route('admin.dashboard'))->assertOk();

        [, $spaAgain] = $this->loginSpa($admin->email);
        $this->defaultCookies = [];
        $this->withCookie($bladeName, $bladeSession)->post(route('admin.logout'))->assertRedirect(route('admin.login'));
        $this->spaJson('GET', '/api/v1/me', cookies: [$this->cookieName() => $spaAgain])->assertOk();
        $this->assertDatabaseHas('sessions', ['id' => $spaAgain, 'user_id' => $admin->id]);
    }

    public function test_inactive_session_user_gets_the_existing_403_and_every_credential_is_purged(): void
    {
        $this->enableSpaSession();
        $user = User::factory()->create();
        $other = User::factory()->create();
        [, $sessionId] = $this->loginSpa($user->email);
        [, $otherSession] = $this->loginSpa($other->email);
        $user->createToken('api-token');
        $this->createDurableSession($user);
        $user->forceFill(['active' => false])->save();

        $this->spaJson('GET', '/api/v1/me', cookies: [$this->cookieName() => $sessionId])
            ->assertForbidden()
            ->assertExactJson(['message' => 'El usuario está inactivo.', 'data' => null]);

        $this->assertCredentialCounts($user, tokens: 0, sessions: 0);
        $this->assertDatabaseHas('sessions', ['id' => $otherSession, 'user_id' => $other->id]);

        $this->spaJson('GET', '/api/v1/me', cookies: [$this->cookieName() => $sessionId])->assertUnauthorized();
        $this->assertCredentialCounts($user, tokens: 0, sessions: 0);
    }

    public function test_password_reset_style_revocation_removes_spa_sessions(): void
    {
        $this->enableSpaSession();
        $user = User::factory()->create();
        [, $sessionId] = $this->loginSpa($user->email);

        app(UserAuthenticationRevocationService::class)->revokeAll($user);

        $this->spaJson('GET', '/api/v1/me', cookies: [$this->cookieName() => $sessionId])->assertUnauthorized();
    }

    public function test_anonymous_and_public_traffic_stays_stateless_even_with_the_mode_header(): void
    {
        $this->enableSpaSession();

        foreach ([true, false] as $sessionMode) {
            $this->spaJson('GET', '/api/v1/seasons', sessionMode: $sessionMode)
                ->assertOk()->assertCookieMissing($this->cookieName());
            $this->spaJson('GET', '/api/v1/me', sessionMode: $sessionMode)->assertUnauthorized();
            $this->spaJson('POST', '/api/v1/auth/forgot-password', ['email' => 'nobody@example.test'], sessionMode: $sessionMode)
                ->assertOk()->assertCookieMissing($this->cookieName());
            $this->spaJson('POST', '/api/v1/auth/reset-password', [], sessionMode: $sessionMode)
                ->assertUnprocessable();
            $this->spaJson('POST', '/api/v1/auth/login', ['email' => 'nobody@example.test', 'password' => 'x'], sessionMode: $sessionMode)
                ->assertUnauthorized();
            foreach ([
                '/api/v1/contact-requests',
                '/api/v1/school/enrollments',
                '/api/v1/public-identity/confirmation/lookup',
            ] as $uri) {
                $status = $this->spaJson('POST', $uri, [], sessionMode: $sessionMode)->getStatusCode();
                $this->assertNotSame(419, $status, $uri);
            }
        }

        $this->assertSame(0, $this->sessionRows());
    }

    public function test_credentialed_cors_and_preflight_allow_only_the_exact_origin_and_transition_headers(): void
    {
        $this->enableSpaSession();
        $preflight = function (string $origin) {
            $this->flushHeaders();

            return $this->call('OPTIONS', '/api/v1/auth/session/login', [], [], [], [
                'HTTP_ORIGIN' => $origin,
                'HTTP_ACCESS_CONTROL_REQUEST_METHOD' => 'POST',
                'HTTP_ACCESS_CONTROL_REQUEST_HEADERS' => 'content-type,x-csrf-token,x-galotxas-auth-mode',
            ]);
        };

        $allowed = $preflight(self::SPA_ORIGIN);
        $allowed->assertNoContent();
        $this->assertSame(self::SPA_ORIGIN, $allowed->headers->get('Access-Control-Allow-Origin'));
        $this->assertSame('true', $allowed->headers->get('Access-Control-Allow-Credentials'));
        $headers = strtolower($allowed->headers->get('Access-Control-Allow-Headers'));
        $this->assertStringContainsString('x-csrf-token', $headers);
        $this->assertStringContainsString('x-galotxas-auth-mode', $headers);
        $this->assertStringContainsString('authorization', $headers);

        foreach (['https://evil.example', 'https://staging.galotxesmonover.es'] as $origin) {
            $response = $preflight($origin);
            $this->assertNotSame($origin, $response->headers->get('Access-Control-Allow-Origin'));
            $this->assertNotSame('*', $response->headers->get('Access-Control-Allow-Origin'));
        }
    }
}
