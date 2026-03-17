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
        $dominantHandOptions = [
            'right' => 'Derecha',
            'left' => 'Izquierda',
            'both' => 'Ambas',
        ];

        return view('admin.players.create', compact('users', 'genderOptions', 'dominantHandOptions'));
    }

    public function store(StorePlayerRequest $request)
    {
        $validated = $request->validated();

        $user = User::findOrFail($validated['user_id']);

        Player::create([
            'user_id' => $validated['user_id'],
            'nickname' => $validated['nickname'] ?? null,
            'slug' => $this->generateUniqueSlug(
                $this->resolveSlugBase($validated['nickname'] ?? null, $user)
            ),
            'dni' => $validated['dni'] ?? null,
            'birth_date' => $validated['birth_date'] ?? null,
            'gender' => $validated['gender'] ?? null,
            'level' => $validated['level'],
            'license_number' => $validated['license_number'] ?? null,
            'dominant_hand' => $validated['dominant_hand'] ?? null,
            'notes' => $validated['notes'] ?? null,
            'active' => $validated['active'] ?? false,
        ]);

        return redirect()
            ->route('admin.players.index')
            ->with('success', 'Jugador creado correctamente.');
    }

    public function show(Player $player)
    {
        $player->load('user', 'teams', 'entries.category');

        return view('admin.players.show', compact('player'));
    }

    public function edit(Player $player)
    {
        $users = User::whereDoesntHave('player')
            ->orWhere('id', $player->user_id)
            ->orderBy('name')
            ->get();

        $genderOptions = PlayerGender::options();
        $dominantHandOptions = [
            'right' => 'Derecha',
            'left' => 'Izquierda',
            'both' => 'Ambas',
        ];

        return view('admin.players.edit', compact('player', 'users', 'genderOptions', 'dominantHandOptions'));
    }

    public function update(UpdatePlayerRequest $request, Player $player)
    {
        $validated = $request->validated();

        $userChanged = (int) $player->user_id !== (int) $validated['user_id'];
        $nicknameChanged = ($player->nickname ?? null) !== ($validated['nickname'] ?? null);

        $data = [
            'user_id' => $validated['user_id'],
            'nickname' => $validated['nickname'] ?? null,
            'dni' => $validated['dni'] ?? null,
            'birth_date' => $validated['birth_date'] ?? null,
            'gender' => $validated['gender'] ?? null,
            'level' => $validated['level'],
            'license_number' => $validated['license_number'] ?? null,
            'dominant_hand' => $validated['dominant_hand'] ?? null,
            'notes' => $validated['notes'] ?? null,
            'active' => $validated['active'] ?? false,
        ];

        if ($userChanged || $nicknameChanged) {
            $user = User::findOrFail($validated['user_id']);

            $data['slug'] = $this->generateUniqueSlug(
                $this->resolveSlugBase($validated['nickname'] ?? null, $user),
                $player->id
            );
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

    private function resolveSlugBase(?string $nickname, User $user): string
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
