<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Concerns\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\RegisterUserRequest;
use App\Models\User;
use App\Services\AccountAuthenticationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Autenticación por sesión de primera parte para la SPA (transición 5.7-J).
 * Sólo es alcanzable en modo sesión (SpaSessionMode + RequireSpaSessionMode).
 * Nunca emite ni revoca personal access tokens.
 */
class SpaSessionAuthController extends Controller
{
    use ApiResponse;

    public function csrf(Request $request): JsonResponse
    {
        return $this->successResponse(['csrf_token' => $request->session()->token()]);
    }

    public function login(Request $request, AccountAuthenticationService $accounts): JsonResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        $user = $accounts->findByCredentials($validated['email'], $validated['password']);

        if (! $user) {
            return $this->errorResponse('Credenciales incorrectas.', [], 401);
        }

        if (! $user->active) {
            return $this->errorResponse('El usuario está inactivo.', [], 403);
        }

        $this->startAuthenticatedSession($request, $user);

        return $this->successResponse([
            ...$accounts->loginPayload($user),
            'csrf_token' => $request->session()->token(),
        ], 'Login correcto.');
    }

    public function register(RegisterUserRequest $request, AccountAuthenticationService $accounts): JsonResponse
    {
        $user = $accounts->register($request->validated());

        $this->startAuthenticatedSession($request, $user);

        return $this->successResponse([
            ...$accounts->registrationPayload($user),
            'csrf_token' => $request->session()->token(),
        ], 'Registro correcto.', status: 201);
    }

    public function logout(Request $request): JsonResponse
    {
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return $this->successResponse([
            'csrf_token' => $request->session()->token(),
        ], 'Logout correcto.');
    }

    private function startAuthenticatedSession(Request $request, User $user): void
    {
        Auth::guard('web')->login($user);
        $request->session()->regenerate(true);
        $request->session()->regenerateToken();
    }
}
