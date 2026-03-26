<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Concerns\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\UpdateMyPlayerProfileRequest;
use App\Http\Resources\PlayerProfileResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use App\Enums\UserRole;
use App\Http\Requests\Api\RegisterUserRequest;
use App\Models\User;
use App\Http\Requests\Api\CreateMyPlayerProfileRequest;
use App\Models\Player;
use Illuminate\Support\Str;
use App\Http\Requests\Api\ForgotPasswordRequest;
use App\Http\Requests\Api\ResetPasswordRequest;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Support\Facades\Password;

class AuthController extends Controller
{
    use ApiResponse;

    public function register(RegisterUserRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $user = User::create([
            'name' => $validated['name'],
            'lastname' => $validated['lastname'],
            'email' => $validated['email'],
            'password' => $validated['password'],
            'role' => UserRole::USER->value,
            'active' => true,
        ]);

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
                'has_player' => false,
            ],
            'player' => null,
        ], 'Registro correcto.', status: 201);
    }

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

    public function createMyPlayerProfile(
        CreateMyPlayerProfileRequest $request
    ): JsonResponse {
        $user = $request->user()->load('player');

        if ($user->player) {
            return $this->errorResponse(
                'El usuario autenticado ya tiene un perfil de jugador asociado.',
                [],
                409
            );
        }

        $validated = $request->validated();

        $player = Player::create([
            'user_id' => $user->id,
            'nickname' => $validated['nickname'] ?? null,
            'slug' => $this->generateUniquePlayerSlug(
                $this->resolvePlayerSlugBase($validated['nickname'] ?? null, $user)
            ),
            'dni' => $validated['dni'] ?? null,
            'birth_date' => $validated['birth_date'] ?? null,
            'gender' => $validated['gender'] ?? null,
            'level' => $validated['level'],
            'license_number' => $validated['license_number'] ?? null,
            'dominant_hand' => $validated['dominant_hand'] ?? null,
            'notes' => $validated['notes'] ?? null,
            'active' => true,
        ]);

        $player->load('user');

        return $this->successResponse(
            new PlayerProfileResource($player),
            'Perfil de jugador creado correctamente.',
            201
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

    public function forgotPassword(ForgotPasswordRequest $request): JsonResponse
    {
        $status = Password::sendResetLink([
            'email' => $request->validated('email'),
        ]);

        if ($status !== Password::RESET_LINK_SENT) {
            return $this->errorResponse(__($status), [], 422);
        }

        return $this->successResponse(
            null,
            'Si el correo existe, se ha enviado un enlace para restablecer la contraseña.'
        );
    }

    public function resetPassword(ResetPasswordRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $status = Password::reset(
            [
                'email' => $validated['email'],
                'password' => $validated['password'],
                'password_confirmation' => $validated['password_confirmation'],
                'token' => $validated['token'],
            ],
            function ($user, $password) {
                $user->forceFill([
                    'password' => $password,
                    'remember_token' => Str::random(60),
                ])->save();

                event(new PasswordReset($user));
            }
        );

        if ($status !== Password::PASSWORD_RESET) {
            return $this->errorResponse(__($status), [], 422);
        }

        return $this->successResponse(
            null,
            'Contraseña restablecida correctamente.'
        );
    }

    private function resolvePlayerSlugBase(?string $nickname, User $user): string
    {
        if (!empty($nickname)) {
            return $nickname;
        }

        $fullName = trim(($user->name ?? '') . ' ' . ($user->lastname ?? ''));

        if ($fullName !== '') {
            return $fullName;
        }

        return $user->name ?: 'player';
    }

    private function generateUniquePlayerSlug(string $base, ?int $ignorePlayerId = null): string
    {
        $slug = Str::slug($base);

        if ($slug === '') {
            $slug = 'player';
        }

        $originalSlug = $slug;
        $counter = 1;

        while (
        Player::when($ignorePlayerId, fn ($query) => $query->where('id', '!=', $ignorePlayerId))
            ->where('slug', $slug)
            ->exists()
        ) {
            $slug = $originalSlug . '-' . $counter;
            $counter++;
        }

        return $slug;
    }
}
