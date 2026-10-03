<?php

namespace Tests\Concerns;

use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;

trait InteractsWithSpaSession
{
    protected const SPA_ORIGIN = 'https://galotxesmonover.es';

    protected function enableSpaSession(): void
    {
        config()->set('spa_session.enabled', true);
        config()->set('spa_session.cookie', 'galotxas-spa-session');
        config()->set('session.cookie', 'galotxas-session');
        config()->set('session.driver', 'database');
        config()->set('session.secure', true);
        config()->set('app.frontend_url', self::SPA_ORIGIN);
        config()->set('cors.allowed_origins', [self::SPA_ORIGIN]);
        config()->set('cors.supports_credentials', true);
    }

    /** @param array<string, string> $cookies  */
    protected function spaJson(
        string $method,
        string $uri,
        array $data = [],
        array $cookies = [],
        array $headers = [],
        bool $sessionMode = true,
    ): TestResponse {
        $this->flushHeaders();
        $this->defaultCookies = [];
        $this->unencryptedCookies = [];

        $base = ['Origin' => self::SPA_ORIGIN];

        if ($sessionMode) {
            $base['X-Galotxas-Auth-Mode'] = 'session';
        }

        // Cada petición HTTP real parte de un proceso limpio; aquí se simula.
        $this->app['session']->forgetDrivers();
        $this->app->forgetInstance('session.store');
        $this->app['auth']->forgetGuards();
        $this->app->forgetInstance('auth.driver');
        $this->withCredentials();
        $this->withHeaders(array_merge($base, $headers))->withCookies($cookies);

        return $this->json($method, $uri, $data);
    }

    protected function spaCookie(TestResponse $response): ?string
    {
        return $response->getCookie((string) config('spa_session.cookie'))?->getValue();
    }

    /** @return array{string, string} CSRF token y ID de sesión SPA anónima */
    protected function bootstrapSpaSession(): array
    {
        $response = $this->spaJson('GET', '/api/v1/auth/csrf')->assertOk();

        return [$response->json('data.csrf_token'), $this->spaCookie($response)];
    }

    /** @return array{string, string} CSRF token e ID de sesión SPA autenticada */
    protected function loginSpa(string $email, string $password = 'password'): array
    {
        [$csrf, $sessionId] = $this->bootstrapSpaSession();

        $response = $this->spaJson(
            'POST',
            '/api/v1/auth/session/login',
            ['email' => $email, 'password' => $password],
            [config('spa_session.cookie') => $sessionId],
            ['X-CSRF-TOKEN' => $csrf],
        )->assertOk();

        return [$response->json('data.csrf_token'), $this->spaCookie($response)];
    }

    protected function sessionRows(): int
    {
        return DB::table('sessions')->count();
    }
}
