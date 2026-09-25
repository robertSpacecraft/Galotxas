<?php

namespace App\Http\Controllers\Admin;

use App\Enums\ChampionshipType;
use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Team;
use App\Services\CategoryEntryService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CategoryTeamController extends Controller
{
    public function store(
        Request $request,
        Category $category,
        CategoryEntryService $entries,
    ) {
        $category->loadMissing('championship');

        if ($category->championship->type !== ChampionshipType::DOUBLES) {
            return back()->with('error', 'Solo se pueden formar equipos en categorías de dobles');
        }

        $validated = $request->validate([
            'name' => ['nullable', 'string', 'max:255'],
            'front_player_id' => ['required', 'exists:players,id'],
            'back_player_id' => ['required', 'exists:players,id', 'different:front_player_id'],
        ]);

        $frontPlayerId = (int) $validated['front_player_id'];
        $backPlayerId = (int) $validated['back_player_id'];

        $error = DB::transaction(function () use (
            $validated,
            $category,
            $frontPlayerId,
            $backPlayerId,
            $entries,
        ): ?string {
            $lock = $entries->lockForParticipantMutation($category);

            $entries->assertPlayersRegistered($lock, [$frontPlayerId, $backPlayerId]);

            $alreadyAssignedIds = $entries->assignedTeamPlayerIds($lock);

            if (
                $alreadyAssignedIds->contains($frontPlayerId) ||
                $alreadyAssignedIds->contains($backPlayerId)
            ) {
                return 'Uno o ambos jugadores ya pertenecen a otro equipo de esta categoría';
            }

            $teamName = $validated['name'];

            if (! $teamName) {
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
                    ?: trim(($frontPlayer?->user?->name ?? '').' '.($frontPlayer?->user?->lastname ?? ''));

                $backName = $backPlayer?->nickname
                    ?: trim(($backPlayer?->user?->name ?? '').' '.($backPlayer?->user?->lastname ?? ''));

                $teamName = 'Equipo '.$frontName.' / '.$backName;
            }

            $team = Team::create([
                'category_id' => $category->id,
                'name' => $teamName,
            ]);

            $team->players()->attach($frontPlayerId, ['role_in_team' => 'front']);
            $team->players()->attach($backPlayerId, ['role_in_team' => 'back']);

            $entries->createForTeam($lock, $team->id);

            return null;
        });

        if ($error !== null) {
            return back()->with('error', $error);
        }

        return back()->with('success', 'Equipo creado correctamente');
    }

    public function destroy(
        Category $category,
        Team $team,
        CategoryEntryService $entries,
    ) {
        $category->loadMissing('championship');

        if ($team->category_id !== $category->id) {
            abort(404);
        }

        if ($category->championship->type !== ChampionshipType::DOUBLES) {
            return back()->with('error', 'Esta acción solo aplica a categorías de dobles');
        }

        DB::transaction(function () use ($category, $team, $entries) {
            $lock = $entries->lockForParticipantMutation($category);

            $entries->deleteForTeam($lock, $team->id);

            $team->delete();
        });

        return back()->with('success', 'Equipo eliminado correctamente');
    }
}
