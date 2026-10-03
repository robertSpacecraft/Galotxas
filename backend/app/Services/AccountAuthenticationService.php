<?php

namespace App\Services;

use App\Enums\UserRole;
use App\Http\Resources\PlayerProfileResource;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

/**
 * Lógica de cuenta compartida por el login/registro Bearer y por la sesión SPA.
 * No emite credenciales: cada modo decide cómo autenticar.
 */
class AccountAuthenticationService
{
    public function __construct(private readonly ProfileDeclarationService $declarations) {}

    /** @param array<string, mixed> $validated */
    public function register(array $validated): User
    {
        return DB::transaction(function () use ($validated): User {
            $user = User::create([
                'name' => $validated['name'],
                'lastname' => $validated['lastname'],
                'email' => $validated['email'],
                'password' => $validated['password'],
                'role' => UserRole::USER->value,
                'active' => true,
            ]);

            $this->declarations->recordGeneral($user);

            return $user;
        });
    }

    public function findByCredentials(string $email, string $password): ?User
    {
        $user = User::query()
            ->with(['player.user', 'player.publicIdentityAuthorizations'])
            ->where('email', $email)
            ->first();

        if (! $user || ! Hash::check($password, $user->password)) {
            return null;
        }

        return $user;
    }

    /** @return array<string, mixed> */
    public function registrationPayload(User $user): array
    {
        return [
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
        ];
    }

    /** @return array<string, mixed> */
    public function loginPayload(User $user): array
    {
        return [
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'lastname' => $user->lastname,
                'email' => $user->email,
                'role' => $user->role,
                'active' => $user->active,
                'has_player' => $user->player !== null,
                'profile_declaration_required' => ! $this->declarations->hasRecognizedGeneral($user),
            ],
            'player' => $user->player ? new PlayerProfileResource($user->player->load('user')) : null,
        ];
    }
}
