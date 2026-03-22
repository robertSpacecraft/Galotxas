<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Concerns\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\UpdateMyPlayerProfileRequest;
use App\Http\Resources\PlayerProfileResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    use ApiResponse;

    public function login(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        $user = \App\Models\User::query()
            ->with('player.user')
            ->where('email', $validated['email'])
            ->first();

        if (!$user || !Hash::check($validated['password'], $user->password)) {
            return $this->errorResponse('Credenciales incorrectas.', [], 401);
        }

        if (!$user->active) {
            return $this->errorResponse('El usuario está inactivo.', [], 403);
        }

        $token = $user->createToken('api-token')->plainTextToken;

        return $this->successResponse([
            'token' => $token,
            'token_type' => 'Bearer',
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'lastname' => $user->lastname,
                'email' => $user->email,
                'role' => $user->role,
                'active' => $user->active,
                'has_player' => $user->player !== null,
            ],
            'player' => $user->player ? new PlayerProfileResource($user->player->load('user')) : null,
        ], 'Login correcto.');
    }

    public function me(Request $request): JsonResponse
    {
        $user = $request->user()->load('player.user');

        return $this->successResponse([
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'lastname' => $user->lastname,
                'email' => $user->email,
                'role' => $user->role,
                'active' => $user->active,
                'has_player' => $user->player !== null,
            ],
            'player' => $user->player ? new PlayerProfileResource($user->player) : null,
        ]);
    }

    public function myPlayerProfile(Request $request): JsonResponse
    {
        $user = $request->user()->load('player.user');

        if (!$user->player) {
            return $this->errorResponse('El usuario autenticado no tiene un perfil de jugador asociado.');
        }

        return $this->successResponse(
            new PlayerProfileResource($user->player)
        );
    }

    public function updateMyPlayerProfile(
        UpdateMyPlayerProfileRequest $request
    ): JsonResponse {
        $user = $request->user()->load('player.user');

        if (!$user->player) {
            return $this->errorResponse('El usuario autenticado no tiene un perfil de jugador asociado.');
        }

        $validated = $request->validated();

        $user->player->update([
            'nickname' => $validated['nickname'] ?? $user->player->nickname,
            'dominant_hand' => $validated['dominant_hand'] ?? $user->player->dominant_hand,
            'notes' => $validated['notes'] ?? $user->player->notes,
        ]);

        $user->player->refresh()->load('user');

        return $this->successResponse(
            new PlayerProfileResource($user->player),
            'Perfil de jugador actualizado correctamente.'
        );
    }
}
