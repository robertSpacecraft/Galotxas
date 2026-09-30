<?php

namespace App\Http\Middleware;

use App\Services\UserAuthenticationRevocationService;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserIsActive
{
    public function __construct(private readonly UserAuthenticationRevocationService $revocations) {}

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && ! $user->active) {
            $this->revocations->revokeAll($user);

            return new JsonResponse([
                'message' => 'El usuario está inactivo.',
                'data' => null,
            ], 403);
        }

        return $next($request);
    }
}
