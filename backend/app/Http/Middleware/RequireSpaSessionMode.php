<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RequireSpaSessionMode
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->attributes->get(SpaSessionMode::ATTRIBUTE) !== true) {
            return new JsonResponse([
                'message' => 'La sesión SPA no está disponible para esta petición.',
                'data' => null,
            ], 403);
        }

        return $next($request);
    }
}
