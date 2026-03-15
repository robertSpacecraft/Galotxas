<?php

namespace App\Http\Controllers\Admin;

use App\Enums\PlayerGender;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StorePlayerRequest;
use App\Http\Requests\Admin\UpdatePlayerRequest;
use App\Models\Player;
use App\Models\User;
use Illuminate\Support\Str;

class PlayerController extends Controller
{
    public function index()
    {
        $players = Player::with('user')
            ->orderByDesc('id')
            ->paginate(15);

        return view('admin.players.index', compact('players'));
    }

    public function create()
    {
        $users = User::doesntHave('player')
            ->orderBy('name')
            ->get();

        $genderOptions = PlayerGender::options();

        return view('admin.players.create', compact('users', 'genderOptions'));
    }

    public function store(StorePlayerRequest $request)
    {
        $validated = $request->validated();

        $user = User::findOrFail($validated['user_id']);

        Player::create([
            'user_id' => $validated['user_id'],
            'slug' => $this->generateUniqueSlug($user->name),
            'dni' => $validated['dni'] ?? null,
            'birth_date' => $validated['birth_date'] ?? null,
            'gender' => $validated['gender'] ?? null,
            'level' => $validated['level'],
            'active' => $validated['active'] ?? false,
        ]);

        return redirect()
            ->route('admin.players.index')
            ->with('success', 'Jugador creado correctamente.');
    }

    public function edit(Player $player)
    {
        $users = User::whereDoesntHave('player')
            ->orWhere('id', $player->user_id)
            ->orderBy('name')
            ->get();

        $genderOptions = PlayerGender::options();

        return view('admin.players.edit', compact('player', 'users', 'genderOptions'));
    }

    public function update(UpdatePlayerRequest $request, Player $player)
    {
        $validated = $request->validated();

        $userChanged = (int) $player->user_id !== (int) $validated['user_id'];

        $data = [
            'user_id' => $validated['user_id'],
            'dni' => $validated['dni'] ?? null,
            'birth_date' => $validated['birth_date'] ?? null,
            'gender' => $validated['gender'] ?? null,
            'level' => $validated['level'],
            'active' => $validated['active'] ?? false,
        ];

        if ($userChanged) {
            $user = User::findOrFail($validated['user_id']);
            $data['slug'] = $this->generateUniqueSlug($user->name, $player->id);
        }

        $player->update($data);

        return redirect()
            ->route('admin.players.index')
            ->with('success', 'Jugador actualizado correctamente.');
    }

    public function destroy(Player $player)
    {
        $player->delete();

        return redirect()
            ->route('admin.players.index')
            ->with('success', 'Jugador eliminado correctamente.');
    }

    private function generateUniqueSlug(string $base, ?int $ignorePlayerId = null): string
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
