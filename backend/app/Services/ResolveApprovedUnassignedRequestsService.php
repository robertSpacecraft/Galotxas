<?php

namespace App\Services;

use App\Enums\ChampionshipRegistrationRequestStatus;
use App\Models\ChampionshipRegistrationRequest;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class ResolveApprovedUnassignedRequestsService
{
    /**
     * Obtiene solicitudes de inscripción aprobadas cuyo jugador
     * aún no ha sido asignado a ninguna categoría del mismo campeonato.
     *
     * Una solicitud se considera "asignada" cuando existe un
     * CategoryRegistration para el mismo player_id en una categoría
     * que pertenece al mismo championship_id de la solicitud.
     */
    public function resolve(int $limit = 20): Collection
    {
        return $this->query()
            ->with([
                'user',
                'player.user',
                'championship.categories' => function ($query) {
                    $query->orderBy('level')->orderBy('name');
                },
                'suggestedCategory',
            ])
            ->latest('updated_at')
            ->limit($limit)
            ->get();
    }

    public function count(): int
    {
        return $this->query()->count();
    }

    private function query(): Builder
    {
        return ChampionshipRegistrationRequest::query()
            ->where('status', ChampionshipRegistrationRequestStatus::APPROVED->value)
            ->whereNotNull('player_id')
            ->whereNotExists(function ($sub) {
                $sub->select(DB::raw(1))
                    ->from('category_registrations')
                    ->join('categories', 'categories.id', '=', 'category_registrations.category_id')
                    ->whereColumn('category_registrations.player_id', 'championship_registration_requests.player_id')
                    ->whereColumn('categories.championship_id', 'championship_registration_requests.championship_id');
            });
    }
}
