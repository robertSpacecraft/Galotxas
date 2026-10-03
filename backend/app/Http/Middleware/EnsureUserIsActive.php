<?php

namespace App\Http\Middleware;

use App\Services\UserAuthenticationRevocationService;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserIsActive
{
    public function __construct(private readonly UserAuthenticationRevocationService $revocations) {}

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && ! $user->active) {
            $this->revocations->revokeAll($user);

            if ($request->hasSession()) {
                // Sesión SPA: evita que el guardado final recree la fila autenticada.
                Auth::guard('web')->logout();
                $request->session()->invalidate();
            }

            return new JsonResponse([
                'message' => 'El usuario está inactivo.',
                'data' => null,
            ], 403);
        }

        return $next($request);
    }
}
