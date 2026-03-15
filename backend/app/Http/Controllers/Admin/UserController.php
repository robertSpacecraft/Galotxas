<?php

namespace App\Http\Controllers\Admin;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreUserRequest;
use App\Http\Requests\Admin\UpdateUserRequest;
use App\Models\User;

class UserController extends Controller
{
    public function index()
    {
        $users = User::with('player')
            ->orderByDesc('id')
            ->paginate(15);

        return view('admin.users.index', compact('users'));
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
            'email' => $validated['email'],
            'password' => $validated['password'],
            'role' => $validated['role'],
            'active' => $validated['active'] ?? false,
        ]);

        return redirect()
            ->route('admin.users.index')
            ->with('success', 'Usuario creado correctamente.');
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
