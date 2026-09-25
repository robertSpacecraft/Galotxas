<?php

namespace App\Http\Controllers\Admin;

use App\Enums\ChampionshipType;
use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\CategoryRegistration;
use App\Models\ChampionshipRegistrationRequest;
use App\Services\CategoryEntryService;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CategoryRegistrationController extends Controller
{
    public function store(
        Request $request,
        Category $category,
        CategoryEntryService $entries,
    ) {
        $validated = $request->validate([
            'player_id' => ['required', 'exists:players,id'],
        ]);

        $playerId = (int) $validated['player_id'];

        $error = DB::transaction(function () use ($category, $playerId, $entries): ?string {
            $lock = $entries->lockForParticipantMutation($category);
            $championshipId = $lock->category->championship_id;

            $hasApprovedChampionshipRequest = ChampionshipRegistrationRequest::query()
                ->where('championship_id', $championshipId)
                ->where('player_id', $playerId)
                ->where('status', 'approved')
                ->exists();

            if (! $hasApprovedChampionshipRequest) {
                return 'El jugador no tiene una solicitud aprobada en este campeonato.';
            }

            $existsInThisCategory = CategoryRegistration::query()
                ->where('category_id', $lock->category->id)
                ->where('player_id', $playerId)
                ->exists();

            if ($existsInThisCategory) {
                return 'El jugador ya está inscrito en esta categoría.';
            }

            $existsInAnotherCategoryOfSameChampionship = CategoryRegistration::query()
                ->where('player_id', $playerId)
                ->whereHas('category', function ($query) use ($championshipId, $lock) {
                    $query->where('championship_id', $championshipId)
                        ->where('id', '!=', $lock->category->id);
                })
                ->exists();

            if ($existsInAnotherCategoryOfSameChampionship) {
                return 'El jugador ya está asignado a otra categoría de este campeonato.';
            }

            try {
                CategoryRegistration::create([
                    'category_id' => $lock->category->id,
                    'player_id' => $playerId,
                    'status' => 'approved',
                ]);
            } catch (QueryException $exception) {
                if ($entries->isDuplicateRegistration($exception)) {
                    return 'El jugador ya está inscrito en esta categoría.';
                }

                throw $exception;
            }

            if ($entries->modality($lock) === ChampionshipType::SINGLES) {
                $entries->ensureForPlayer($lock, $playerId);
            }

            return null;
        });

        if ($error !== null) {
            return back()->with('error', $error);
        }

        return back()->with('success', 'Jugador inscrito correctamente en la categoría.');
    }

    public function destroy(
        Category $category,
        CategoryRegistration $registration,
        CategoryEntryService $entries,
    ) {
        if ($registration->category_id !== $category->id) {
            abort(404);
        }

        $playerId = (int) $registration->player_id;

        $error = DB::transaction(function () use ($category, $registration, $playerId, $entries): ?string {
            $lock = $entries->lockForParticipantMutation($category);

            if ($entries->modality($lock) === ChampionshipType::DOUBLES) {
                if ($entries->assignedTeamPlayerIds($lock)->contains($playerId)) {
                    return 'No se puede eliminar la inscripción porque el jugador pertenece a un equipo de esta categoría';
                }
            } else {
                $entries->deleteForPlayer($lock, $playerId);
            }

            $registration->delete();

            return null;
        });

        if ($error !== null) {
            return back()->with('error', $error);
        }

        return back()->with('success', 'Inscripción eliminada');
    }
}
