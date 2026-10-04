<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Puerta de emisión de personal access tokens (login/registro legacy).
 *
 * Salvo LEGACY_BEARER_ISSUANCE_ENABLED=true (valor booleano exacto) rechaza con 403 antes de validar,
 * autenticar o crear nada. Debe declararse después del limiter de la ruta.
 */
class RequireLegacyBearerIssuance
{
    public function handle(Request $request, Closure $next): Response
    {
        if (config('legacy_bearer.issuance_enabled') !== true) {
            return new JsonResponse([
                'message' => 'La emisión de credenciales Bearer está desactivada. Usa la sesión SPA.',
                'data' => null,
            ], 403);
        }

        return $next($request);
    }
}
