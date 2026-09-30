<?php

namespace App\Http\Controllers\Admin;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreUserRequest;
use App\Http\Requests\Admin\UpdateUserRequest;
use App\Models\User;
use App\Services\ProfilePhotoService;
use App\Services\UserAuthenticationRevocationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class UserController extends Controller
{
    public function index(Request $request)
    {
        $playerFilter = $request->get('player_filter', 'all');
        $search = trim($request->string('q')->toString());

        $query = User::with('player')->orderByDesc('id');

        if ($playerFilter === 'with_player') {
            $query->has('player');
        }

        if ($playerFilter === 'without_player') {
            $query->doesntHave('player');
        }

        if ($search !== '') {
            $pattern = '%'.addcslashes($search, '\\%_').'%';

            $query->where(fn ($users) => $users
                ->where('name', 'like', $pattern)
                ->orWhere('lastname', 'like', $pattern)
                ->orWhere('email', 'like', $pattern)
                ->orWhereHas('player', fn ($player) => $player->where('nickname', 'like', $pattern)));
        }

        $users = $query->paginate(15)->appends([
            'player_filter' => $playerFilter,
            'q' => $search !== '' ? $search : null,
        ]);

        return view('admin.users.index', compact('users', 'playerFilter', 'search'));
    }

    public function create()
    {
        $roleOptions = [
            UserRole::ADMIN->value => 'Administrador',
            UserRole::USER->value => 'Usuario',
        ];

        return view('admin.users.create', compact('roleOptions'));
    }

    public function store(StoreUserRequest $request)
    {
        $validated = $request->validated();

        User::create([
            'name' => $validated['name'],
            'lastname' => $validated['lastname'],
            'email' => $validated['email'],
            'password' => $validated['password'],
            'role' => $validated['role'],
            'active' => $validated['active'] ?? false,
        ]);

        return redirect()
            ->route('admin.users.index')
            ->with('success', 'Usuario creado correctamente.');
    }

    public function show(User $user)
    {
        $user->load('player');

        return view('admin.users.show', compact('user'));
    }

    public function edit(User $user)
    {
        $roleOptions = [
            UserRole::ADMIN->value => 'Administrador',
            UserRole::USER->value => 'Usuario',
        ];

        return view('admin.users.edit', compact('user', 'roleOptions'));
    }

    public function update(
        UpdateUserRequest $request,
        User $user,
        UserAuthenticationRevocationService $revocations
    ) {
        $validated = $request->validated();

        $data = [
            'name' => $validated['name'],
            'lastname' => $validated['lastname'],
            'email' => $validated['email'],
            'role' => $validated['role'],
            'active' => $validated['active'] ?? false,
        ];

        $passwordChanged = ! empty($validated['password']);
        $deactivated = $user->active && ! $data['active'];

        if ($passwordChanged) {
            $data['password'] = $validated['password'];
        }

        $revokeCredentials = $passwordChanged || $deactivated;

        DB::transaction(function () use ($user, $data, $revokeCredentials, $revocations): void {
            $user->update($data);

            if ($revokeCredentials) {
                $revocations->revokeAll($user);
            }
        });

        if ($revokeCredentials && $request->user()->is($user)) {
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('admin.login')->withErrors([
                'email' => $deactivated
                    ? 'Tu usuario está inactivo.'
                    : 'Tu contraseña se ha actualizado. Vuelve a iniciar sesión.',
            ]);
        }

        return redirect()
            ->route('admin.users.index')
            ->with('success', 'Usuario actualizado correctamente.');
    }

    public function destroy(User $user, ProfilePhotoService $profilePhotos)
    {
        if ($user->player) {
            return redirect()
                ->route('admin.users.index')
                ->with('error', 'No se puede eliminar el usuario porque tiene un jugador asociado. Puedes desactivarlo.');
        }

        $profilePhotos->deleteUser($user);

        return redirect()
            ->route('admin.users.index')
            ->with('success', 'Usuario eliminado correctamente.');
    }
}
