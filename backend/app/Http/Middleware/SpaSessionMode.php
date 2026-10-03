<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Http\Request;
use Illuminate\Routing\Pipeline;
use Illuminate\Session\Middleware\StartSession;
use Laravel\Sanctum\Http\Middleware\AuthenticateSession;
use Symfony\Component\HttpFoundation\Response;

/**
 * Discriminador explícito Bearer/sesión durante la transición 5.7-J.
 *
 * Una petición entra en modo sesión sólo si TODO esto se cumple: la capacidad
 * está activa, no lleva cabecera Authorization, envía X-Galotxas-Auth-Mode:
 * session, su Origin es exactamente FRONTEND_URL y además la ruta es una ruta
 * de sesión (RequireSpaSessionMode) o protegida (`auth:*`) con la cookie SPA
 * presente. En cualquier otro caso pasa sin cookies, sin sesión y sin CSRF
 * (modo Bearer/anónimo vigente), de modo que la navegación pública anónima no
 * crea filas de sesión aunque el cliente envíe la cabecera.
 *
 * El pipeline reutiliza los middleware del framework y de Sanctum. La cookie
 * de la SPA se aplica con configuración acotada a la petición y se restaura al
 * terminar, para que no se filtre a otras peticiones del mismo proceso.
 */
class SpaSessionMode
{
    public const ATTRIBUTE = 'galotxas.spa_session';

    public function __construct(private readonly Application $app) {}

    public function handle(Request $request, Closure $next): Response
    {
        if (! $this->requestsSessionMode($request) || ! $this->routeUsesSession($request)) {
            return $next($request);
        }

        $request->attributes->set(self::ATTRIBUTE, true);

        if (! $request->expectsJson()) {
            $request->headers->set('Accept', 'application/json');
        }

        $original = config('session');
        config(['session' => array_merge($original, [
            'cookie' => config('spa_session.cookie'),
            'domain' => null,
            'path' => '/',
            'http_only' => true,
            'same_site' => 'lax',
            'partitioned' => false,
        ])]);
        $this->resetSessionState('web');

        try {
            $response = (new Pipeline($this->app))
                ->send($request)
                ->through([
                    EncryptCookies::class,
                    AddQueuedCookiesToResponse::class,
                    StartSession::class,
                    SpaSessionCsrfToken::class,
                    AuthenticateSession::class,
                ])
                ->then(fn (Request $request) => $next($request));
        } finally {
            config(['session' => $original]);
            $this->resetSessionState();
        }

        $response->headers->set('Cache-Control', 'no-store, private');

        return $response;
    }

    private function requestsSessionMode(Request $request): bool
    {
        if (config('spa_session.enabled') !== true || $request->headers->has('Authorization')) {
            return false;
        }

        $mode = strtolower(trim((string) $request->headers->get((string) config('spa_session.mode_header'))));
        $origin = (string) $request->headers->get('Origin');

        return $mode === config('spa_session.mode_value')
            && $origin !== ''
            && $origin === rtrim((string) config('app.frontend_url'), '/');
    }

    private function routeUsesSession(Request $request): bool
    {
        $middleware = $request->route()?->gatherMiddleware() ?? [];

        if (in_array(RequireSpaSessionMode::class, $middleware, true)) {
            return true;
        }

        $protected = array_filter(
            $middleware,
            static fn ($name): bool => is_string($name) && str_starts_with($name, 'auth')
        );

        return $protected !== [] && $request->cookies->has((string) config('spa_session.cookie'));
    }

    /**
     * Descarta almacén de sesión y guards cacheados. En modo sesión, el guard
     * por defecto del contrato Guard (el que graba `sessions.user_id`) se fija
     * a `web`: en rutas `auth:sanctum` Laravel lo cambia a `sanctum`, cuyo
     * usuario en memoria podría sobrevivir a un logout de la sesión.
     */
    private function resetSessionState(?string $guard = null): void
    {
        $this->app['session']->forgetDrivers();
        $this->app->forgetInstance('session.store');
        $this->app['auth']->forgetGuards();
        $this->app->forgetInstance('auth.driver');
        $this->app->singleton('auth.driver', fn ($app) => $app['auth']->guard($guard));
    }
}
