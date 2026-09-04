<?php

namespace App\Http\Controllers\Admin;

use App\Enums\ChampionshipType;
use App\Enums\OfficialResultMutationImpact;
use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\CategoryEntry;
use App\Models\CategoryRegistration;
use App\Models\ChampionshipRegistrationRequest;
use App\Services\OfficialResultLockService;
use App\Services\OfficialResultMutationGuard;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CategoryRegistrationController extends Controller
{
    public function store(
        Request $request,
        Category $category,
        OfficialResultMutationGuard $mutationGuard,
        OfficialResultLockService $locks,
    ) {
        $category->loadMissing('championship');

        $validated = $request->validate([
            'player_id' => ['required', 'exists:players,id'],
        ]);

        $playerId = (int) $validated['player_id'];

        $hasApprovedChampionshipRequest = ChampionshipRegistrationRequest::query()
            ->where('championship_id', $category->championship_id)
            ->where('player_id', $playerId)
            ->where('status', 'approved')
            ->exists();

        if (! $hasApprovedChampionshipRequest) {
            return back()->with('error', 'El jugador no tiene una solicitud aprobada en este campeonato.');
        }

        $existsInThisCategory = CategoryRegistration::query()
            ->where('category_id', $category->id)
            ->where('player_id', $playerId)
            ->exists();

        if ($existsInThisCategory) {
            return back()->with('error', 'El jugador ya está inscrito en esta categoría.');
        }

        $existsInAnotherCategoryOfSameChampionship = CategoryRegistration::query()
            ->where('player_id', $playerId)
            ->whereHas('category', function ($query) use ($category) {
                $query->where('championship_id', $category->championship_id)
                    ->where('id', '!=', $category->id);
            })
            ->exists();

        if ($existsInAnotherCategoryOfSameChampionship) {
            return back()->with('error', 'El jugador ya está asignado a otra categoría de este campeonato.');
        }

        DB::transaction(function () use ($category, $playerId, $mutationGuard, $locks) {
            if ($category->championship->type === ChampionshipType::SINGLES) {
                $categoryLock = $mutationGuard->lockAndGuard(
                    $category,
                    OfficialResultMutationImpact::PARTICIPANTS
                );
                $locks->lockRoundsAndMatches([$categoryLock->category->id]);
                $locks->lockEntriesAndTeams([$categoryLock->category->id]);
            }

            CategoryRegistration::create([
                'category_id' => $category->id,
                'player_id' => $playerId,
                'status' => 'approved',
            ]);

            if ($category->championship->type === ChampionshipType::SINGLES) {
                CategoryEntry::firstOrCreate([
                    'category_id' => $category->id,
                    'entry_type' => 'player',
                    'player_id' => $playerId,
                ], [
                    'team_id' => null,
                    'status' => 'approved',
                ]);
            }
        });

        return back()->with('success', 'Jugador inscrito correctamente en la categoría.');
    }

    public function destroy(
        Category $category,
        CategoryRegistration $registration,
        OfficialResultMutationGuard $mutationGuard,
        OfficialResultLockService $locks,
    ) {
        $category->loadMissing('championship');

        if ($registration->category_id !== $category->id) {
            abort(404);
        }

        $playerId = $registration->player_id;

        if ($category->championship->type === ChampionshipType::DOUBLES) {
            $isInTeam = DB::table('team_members')
                ->join('teams', 'teams.id', '=', 'team_members.team_id')
                ->where('teams.category_id', $category->id)
                ->where('team_members.player_id', $playerId)
                ->exists();

            if ($isInTeam) {
                return back()->with('error', 'No se puede eliminar la inscripción porque el jugador pertenece a un equipo de esta categoría');
            }
        }

        DB::transaction(function () use (
            $category,
            $registration,
            $playerId,
            $mutationGuard,
            $locks,
        ) {
            if ($category->championship->type === ChampionshipType::SINGLES) {
                $categoryLock = $mutationGuard->lockAndGuard(
                    $category,
                    OfficialResultMutationImpact::PARTICIPANTS
                );
                $locks->lockRoundsAndMatches([$categoryLock->category->id]);
                $locks->lockEntriesAndTeams([$categoryLock->category->id]);

                CategoryEntry::where('category_id', $category->id)
                    ->where('entry_type', 'player')
                    ->where('player_id', $playerId)
                    ->delete();
            }

            $registration->delete();
        });

        return back()->with('success', 'Inscripción eliminada');
    }
}
