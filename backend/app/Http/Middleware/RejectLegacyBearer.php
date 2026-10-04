<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Puerta de aceptación del Bearer legacy.
 *
 * Con LEGACY_BEARER_ACCEPTANCE_ENABLED=false toda petición con un intento
 * explícito de autenticación Bearer recibe 401 antes de que Sanctum
 * resuelva el token (no se consulta ni se toca `last_used_at`). Otros
 * esquemas de Authorization y las peticiones sin cabecera no se alteran.
 * Se registra en el orden de prioridad antes de `Authenticate`.
 */
class RejectLegacyBearer
{
    public function handle(Request $request, Closure $next): Response
    {
        if (config('legacy_bearer.acceptance_enabled') === false && $this->attemptsBearer($request)) {
            return new JsonResponse([
                'message' => 'Unauthenticated.',
                'data' => null,
            ], 401);
        }

        return $next($request);
    }

    private function attemptsBearer(Request $request): bool
    {
        return preg_match('/^\s*Bearer(\s|$)/i', (string) $request->headers->get('Authorization')) === 1;
    }
}
