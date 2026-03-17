<?php

namespace App\Http\Controllers\Admin;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreUserRequest;
use App\Http\Requests\Admin\UpdateUserRequest;
use App\Models\User;
use Illuminate\Http\Request;

class UserController extends Controller
{
    public function index(Request $request)
    {
        $playerFilter = $request->get('player_filter', 'all');

        $query = User::with('player')->orderByDesc('id');

        if ($playerFilter === 'with_player') {
            $query->has('player');
        }

        if ($playerFilter === 'without_player') {
            $query->doesntHave('player');
        }

        $users = $query->paginate(15)->appends([
            'player_filter' => $playerFilter,
        ]);

        return view('admin.users.index', compact('users', 'playerFilter'));
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

    public function update(UpdateUserRequest $request, User $user)
    {
        $validated = $request->validated();

        $data = [
            'name' => $validated['name'],
            'lastname' => $validated['lastname'],
            'email' => $validated['email'],
            'role' => $validated['role'],
            'active' => $validated['active'] ?? false,
        ];

        if (!empty($validated['password'])) {
            $data['password'] = $validated['password'];
        }

        $user->update($data);

        return redirect()
            ->route('admin.users.index')
            ->with('success', 'Usuario actualizado correctamente.');
    }

    public function destroy(User $user)
    {
        if ($user->player) {
            return redirect()
                ->route('admin.users.index')
                ->with('error', 'No se puede eliminar el usuario porque tiene un jugador asociado. Puedes desactivarlo.');
        }

        $user->delete();

        return redirect()
            ->route('admin.users.index')
            ->with('success', 'Usuario eliminado correctamente.');
    }
}
