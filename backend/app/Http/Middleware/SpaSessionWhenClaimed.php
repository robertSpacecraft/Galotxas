<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Marca una ruta pública que puede asociar al usuario autenticado por sesión.
 *
 * Sin la cabecera de modo (o con Authorization) la ruta sigue siendo pública
 * y sin estado. Si el cliente declara modo sesión nunca se degrada a anónimo:
 * con la capacidad apagada responde 403 (sesión SPA no disponible) y con ella
 * activa, si la sesión SPA no está autenticada, 401.
 */
class SpaSessionWhenClaimed
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! $this->claimsSession($request)) {
            return $next($request);
        }

        if (config('spa_session.enabled') !== true) {
            return RequireSpaSessionMode::unavailable();
        }

        if ($request->attributes->get(SpaSessionMode::ATTRIBUTE) !== true
            || ! Auth::guard('web')->check()) {
            return new JsonResponse([
                'message' => 'Unauthenticated.',
                'data' => null,
            ], 401);
        }

        return $next($request);
    }

    private function claimsSession(Request $request): bool
    {
        return ! $request->headers->has('Authorization')
            && strtolower(trim((string) $request->headers->get((string) config('spa_session.mode_header'))))
                === config('spa_session.mode_value');
    }
}
