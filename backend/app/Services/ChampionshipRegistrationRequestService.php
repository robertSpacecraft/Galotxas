<?php

namespace App\Services;

use App\Enums\ChampionshipRegistrationPaymentStatus;
use App\Enums\ChampionshipRegistrationRequestStatus;
use App\Models\Championship;
use App\Models\ChampionshipRegistrationRequest;
use App\Models\User;
use InvalidArgumentException;

class ChampionshipRegistrationRequestService
{
    public function submit(
        Championship $championship,
        User $user,
        ?int $suggestedCategoryId = null,
        ?string $comment = null
    ): ChampionshipRegistrationRequest {
        $player = $user->player;

        if (!$player) {
            throw new InvalidArgumentException('El usuario autenticado no tiene un perfil de jugador asociado.');
        }

        if (!$championship->registrationIsOpen()) {
            throw new InvalidArgumentException('Las inscripciones de este campeonato no están abiertas.');
        }

        if ($suggestedCategoryId !== null) {
            $categoryBelongsToChampionship = $championship->categories()
                ->whereKey($suggestedCategoryId)
                ->exists();

            if (!$categoryBelongsToChampionship) {
                throw new InvalidArgumentException('La categoría sugerida no pertenece a este campeonato.');
            }
        }

        $existing = ChampionshipRegistrationRequest::query()
            ->where('championship_id', $championship->id)
            ->where('player_id', $player->id)
            ->first();

        if ($existing) {
            throw new InvalidArgumentException('Ya existe una solicitud de inscripción para este campeonato.');
        }

        return ChampionshipRegistrationRequest::query()->create([
            'championship_id' => $championship->id,
            'user_id' => $user->id,
            'player_id' => $player->id,
            'suggested_category_id' => $suggestedCategoryId,
            'status' => ChampionshipRegistrationRequestStatus::PENDING->value,
            'payment_status' => ChampionshipRegistrationPaymentStatus::PENDING->value,
            'comment' => $comment,
        ])->load([
            'championship',
            'user',
            'player.user',
            'suggestedCategory',
        ]);
    }

    public function approve(ChampionshipRegistrationRequest $request): ChampionshipRegistrationRequest
    {
        $request->update([
            'status' => ChampionshipRegistrationRequestStatus::APPROVED->value,
        ]);

        return $request->fresh([
            'championship',
            'user',
            'player.user',
            'suggestedCategory',
        ]);
    }

    public function reject(ChampionshipRegistrationRequest $request): ChampionshipRegistrationRequest
    {
        $request->update([
            'status' => ChampionshipRegistrationRequestStatus::REJECTED->value,
        ]);

        return $request->fresh([
            'championship',
            'user',
            'player.user',
            'suggestedCategory',
        ]);
    }
}
