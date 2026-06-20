<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserIsActive
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && ! $user->active) {
            $accessToken = $user->currentAccessToken();

            if ($accessToken && method_exists($accessToken, 'delete')) {
                $accessToken->delete();
            }

            return new JsonResponse([
                'message' => 'El usuario está inactivo.',
                'data' => null,
            ], 403);
        }

        return $next($request);
    }
}
