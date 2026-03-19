<?php

namespace App\Http\Controllers\Admin;

use App\Enums\ChampionshipType;
use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\CategoryEntry;
use App\Models\Team;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CategoryTeamController extends Controller
{
    public function store(Request $request, Category $category)
    {
        $category->loadMissing('championship');

        if ($category->championship->type !== ChampionshipType::DOUBLES) {
            return back()->with('error', 'Solo se pueden formar equipos en categorías de dobles');
        }

        $validated = $request->validate([
            'name' => ['nullable', 'string', 'max:255'],
            'front_player_id' => ['required', 'exists:players,id'],
            'back_player_id' => ['required', 'exists:players,id', 'different:front_player_id'],
        ]);

        $registeredPlayerIds = $category->registrations()
            ->where('status', 'approved')
            ->pluck('player_id');

        $frontPlayerId = (int) $validated['front_player_id'];
        $backPlayerId = (int) $validated['back_player_id'];

        if (
            !$registeredPlayerIds->contains($frontPlayerId) ||
            !$registeredPlayerIds->contains($backPlayerId)
        ) {
            return back()->with('error', 'Los jugadores del equipo deben estar inscritos en la categoría');
        }

        $alreadyAssignedIds = DB::table('team_members')
            ->join('teams', 'teams.id', '=', 'team_members.team_id')
            ->where('teams.category_id', $category->id)
            ->pluck('team_members.player_id');

        if (
            $alreadyAssignedIds->contains($frontPlayerId) ||
            $alreadyAssignedIds->contains($backPlayerId)
        ) {
            return back()->with('error', 'Uno o ambos jugadores ya pertenecen a otro equipo de esta categoría');
        }

        DB::transaction(function () use ($validated, $category, $frontPlayerId, $backPlayerId) {
            $teamName = $validated['name'];

            if (!$teamName) {
                $frontRegistration = $category->registrations()
                    ->with('player.user')
                    ->where('player_id', $frontPlayerId)
                    ->first();

                $backRegistration = $category->registrations()
                    ->with('player.user')
                    ->where('player_id', $backPlayerId)
                    ->first();

                $frontPlayer = $frontRegistration?->player;
                $backPlayer = $backRegistration?->player;

                $frontName = $frontPlayer?->nickname
                    ?: trim(($frontPlayer?->user?->name ?? '') . ' ' . ($frontPlayer?->user?->lastname ?? ''));

                $backName = $backPlayer?->nickname
                    ?: trim(($backPlayer?->user?->name ?? '') . ' ' . ($backPlayer?->user?->lastname ?? ''));

                $teamName = 'Equipo ' . $frontName . ' / ' . $backName;
            }

            $team = Team::create([
                'category_id' => $category->id,
                'name' => $teamName,
            ]);

            $team->players()->attach($frontPlayerId, ['role_in_team' => 'front']);
            $team->players()->attach($backPlayerId, ['role_in_team' => 'back']);

            CategoryEntry::firstOrCreate([
                'category_id' => $category->id,
                'entry_type' => 'team',
                'team_id' => $team->id,
            ], [
                'player_id' => null,
                'status' => 'approved',
            ]);
        });

        return back()->with('success', 'Equipo creado correctamente');
    }

    public function destroy(Category $category, Team $team)
    {
        $category->loadMissing('championship');

        if ($team->category_id !== $category->id) {
            abort(404);
        }

        if ($category->championship->type !== ChampionshipType::DOUBLES) {
            return back()->with('error', 'Esta acción solo aplica a categorías de dobles');
        }

        DB::transaction(function () use ($category, $team) {
            CategoryEntry::where('category_id', $category->id)
                ->where('entry_type', 'team')
                ->where('team_id', $team->id)
                ->delete();

            $team->delete();
        });

        return back()->with('success', 'Equipo eliminado correctamente');
    }
}
