<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\UserRole;
use App\Http\Controllers\Concerns\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\CreateMyPlayerProfileRequest;
use App\Http\Requests\Api\ForgotPasswordRequest;
use App\Http\Requests\Api\RegisterUserRequest;
use App\Http\Requests\Api\ResetPasswordRequest;
use App\Http\Requests\Api\UpdateMyPlayerProfileRequest;
use App\Http\Resources\MeResource;
use App\Http\Resources\PlayerProfileResource;
use App\Models\User;
use App\Services\PasswordResetLinkService;
use App\Services\ProfileDeclarationService;
use App\Services\SelfServicePlayerProfileService;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;

class AuthController extends Controller
{
    use ApiResponse;

    public function register(
        RegisterUserRequest $request,
        ProfileDeclarationService $declarations,
    ): JsonResponse {
        $validated = $request->validated();

        $user = DB::transaction(function () use ($validated, $declarations): User {
            $user = User::create([
                'name' => $validated['name'],
                'lastname' => $validated['lastname'],
                'email' => $validated['email'],
                'password' => $validated['password'],
                'role' => UserRole::USER->value,
                'active' => true,
            ]);

            $declarations->recordGeneral($user);

            return $user;
        });

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
                'profile_declaration_required' => false,
            ],
            'player' => null,
        ], 'Registro correcto.', status: 201);
    }

    public function login(Request $request, ProfileDeclarationService $declarations): JsonResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        $user = User::query()
            ->with(['player.user', 'player.publicIdentityAuthorizations'])
            ->where('email', $validated['email'])
            ->first();

        if (! $user || ! Hash::check($validated['password'], $user->password)) {
            return $this->errorResponse('Credenciales incorrectas.', [], 401);
        }

        if (! $user->active) {
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
                'profile_declaration_required' => ! $declarations->hasRecognizedGeneral($user),
            ],
            'player' => $user->player ? new PlayerProfileResource($user->player->load('user')) : null,
        ], 'Login correcto.');
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return $this->successResponse(null, 'Logout correcto.');
    }

    public function me(Request $request): JsonResponse
    {
        $user = $request->user()->load(['player.user', 'player.publicIdentityAuthorizations']);

        return $this->successResponse(new MeResource($user));
    }

    public function myPlayerProfile(Request $request): JsonResponse
    {
        $user = $request->user()->load(['player.user', 'player.publicIdentityAuthorizations']);

        if (! $user->player) {
            return $this->errorResponse('El usuario autenticado no tiene un perfil de jugador asociado.');
        }

        return $this->successResponse(
            new PlayerProfileResource($user->player)
        );
    }

    public function createMyPlayerProfile(
        CreateMyPlayerProfileRequest $request,
        SelfServicePlayerProfileService $profiles,
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

        $player = $profiles->create($user, $validated);

        return $this->successResponse(
            new PlayerProfileResource($player),
            'Perfil de jugador creado correctamente.',
            [],
            201
        );
    }

    public function updateMyPlayerProfile(
        UpdateMyPlayerProfileRequest $request,
        SelfServicePlayerProfileService $profiles,
    ): JsonResponse {
        $user = $request->user()->load(['player.user', 'player.publicIdentityAuthorizations']);

        if (! $user->player) {
            return $this->errorResponse('El usuario autenticado no tiene un perfil de jugador asociado.');
        }

        $validated = $request->validated();

        $player = $profiles->update($user, $validated);

        return $this->successResponse(
            new PlayerProfileResource($player),
            'Perfil de jugador actualizado correctamente.'
        );
    }

    public function forgotPassword(
        ForgotPasswordRequest $request,
        PasswordResetLinkService $passwordResetLinks
    ): JsonResponse {
        $passwordResetLinks->send($request->validated('email'));

        return $this->successResponse(
            null,
            'Si el correo existe, recibirás instrucciones para restablecer la contraseña.'
        );
    }

    public function resetPassword(ResetPasswordRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $status = Password::reset(
            [
                'email' => $validated['email'],
                'password' => $validated['password'],
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
}
