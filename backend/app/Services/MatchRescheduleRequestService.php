<?php

namespace App\Services;

use App\Enums\GameMatchStatus;
use App\Enums\MatchRescheduleRequestStatus;
use App\Enums\MatchResultReportSide;
use App\Models\CategoryEntry;
use App\Models\GameMatch;
use App\Models\MatchRescheduleRequest;
use App\Models\Player;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class MatchRescheduleRequestService
{
    public function submitRequest(
        GameMatch $match,
        User $user,
        string $scheduledDate,
        string $scheduledTime,
        int $venueId,
        ?string $comment = null
    ): MatchRescheduleRequest {
        $player = $user->player;

        if (!$player) {
            throw new InvalidArgumentException('El usuario autenticado no tiene un perfil de jugador asociado.');
        }

        $requestedDateTime = Carbon::createFromFormat('Y-m-d H:i', $scheduledDate . ' ' . $scheduledTime);

        return DB::transaction(function () use ($match, $user, $player, $requestedDateTime, $venueId, $comment) {
            /** @var GameMatch $lockedMatch */
            $lockedMatch = GameMatch::query()
                ->whereKey($match->id)
                ->lockForUpdate()
                ->firstOrFail();

            $lockedMatch->load([
                'homeEntry.player',
                'homeEntry.team.players',
                'awayEntry.player',
                'awayEntry.team.players',
                'round.category.championship',
            ]);

            $this->assertMatchAllowsRescheduleRequests($lockedMatch);

            $side = $this->resolvePlayerSide($lockedMatch, $player);

            /** @var MatchRescheduleRequest|null $sameSideRequest */
            $sameSideRequest = MatchRescheduleRequest::query()
                ->where('game_match_id', $lockedMatch->id)
                ->where('side', $side->value)
                ->first();

            if ($sameSideRequest && (int) $sameSideRequest->player_id !== (int) $player->id) {
                throw new InvalidArgumentException('Tu lado ya ha enviado una solicitud de reprogramación para este partido.');
            }

            $oppositeSide = $side === MatchResultReportSide::HOME
                ? MatchResultReportSide::AWAY
                : MatchResultReportSide::HOME;

            /** @var MatchRescheduleRequest|null $oppositeRequest */
            $oppositeRequest = MatchRescheduleRequest::query()
                ->where('game_match_id', $lockedMatch->id)
                ->where('side', $oppositeSide->value)
                ->first();

            if ($oppositeRequest !== null) {
                throw new InvalidArgumentException('Ya existe una solicitud rival pendiente. Debes confirmarla, no crear una nueva.');
            }

            $this->assertScheduleIsAvailable($lockedMatch, $requestedDateTime, $venueId);

            try {
                /** @var MatchRescheduleRequest $request */
                $request = MatchRescheduleRequest::query()->updateOrCreate(
                    [
                        'game_match_id' => $lockedMatch->id,
                        'side' => $side->value,
                    ],
                    [
                        'user_id' => $user->id,
                        'player_id' => $player->id,
                        'requested_scheduled_date' => $requestedDateTime,
                        'requested_venue_id' => $venueId,
                        'status' => MatchRescheduleRequestStatus::SUBMITTED->value,
                        'comment' => $comment,
                    ]
                );
            } catch (QueryException $exception) {
                throw new InvalidArgumentException('Se ha producido un conflicto al guardar la solicitud. Inténtalo de nuevo.');
            }

            return $request->fresh(['user', 'player.user', 'requestedVenue']);
        });
    }

    public function confirmRequest(
        GameMatch $match,
        User $user
    ): MatchRescheduleRequest {
        $player = $user->player;

        if (!$player) {
            throw new InvalidArgumentException('El usuario autenticado no tiene un perfil de jugador asociado.');
        }

        return DB::transaction(function () use ($match, $user, $player) {
            /** @var GameMatch $lockedMatch */
            $lockedMatch = GameMatch::query()
                ->whereKey($match->id)
                ->lockForUpdate()
                ->firstOrFail();

            $lockedMatch->load([
                'homeEntry.player',
                'homeEntry.team.players',
                'awayEntry.player',
                'awayEntry.team.players',
                'round.category.championship',
            ]);

            $this->assertMatchAllowsRescheduleRequests($lockedMatch);

            $side = $this->resolvePlayerSide($lockedMatch, $player);

            /** @var MatchRescheduleRequest|null $mySideRequest */
            $mySideRequest = MatchRescheduleRequest::query()
                ->where('game_match_id', $lockedMatch->id)
                ->where('side', $side->value)
                ->first();

            if ($mySideRequest !== null) {
                throw new InvalidArgumentException('Tu lado ya ha enviado una solicitud de reprogramación para este partido.');
            }

            $oppositeSide = $side === MatchResultReportSide::HOME
                ? MatchResultReportSide::AWAY
                : MatchResultReportSide::HOME;

            /** @var MatchRescheduleRequest|null $oppositeRequest */
            $oppositeRequest = MatchRescheduleRequest::query()
                ->where('game_match_id', $lockedMatch->id)
                ->where('side', $oppositeSide->value)
                ->first();

            if ($oppositeRequest === null) {
                throw new InvalidArgumentException('No existe ninguna solicitud rival para confirmar.');
            }

            $this->assertScheduleIsAvailable(
                $lockedMatch,
                $oppositeRequest->requested_scheduled_date,
                $oppositeRequest->requested_venue_id
            );

            /** @var MatchRescheduleRequest $confirmation */
            $confirmation = MatchRescheduleRequest::query()->create([
                'game_match_id' => $lockedMatch->id,
                'user_id' => $user->id,
                'player_id' => $player->id,
                'side' => $side->value,
                'requested_scheduled_date' => $oppositeRequest->requested_scheduled_date,
                'requested_venue_id' => $oppositeRequest->requested_venue_id,
                'status' => MatchRescheduleRequestStatus::VALIDATED->value,
                'comment' => null,
            ]);

            $oppositeRequest->update([
                'status' => MatchRescheduleRequestStatus::VALIDATED->value,
            ]);

            $lockedMatch->update([
                'scheduled_date' => $oppositeRequest->requested_scheduled_date,
                'venue_id' => $oppositeRequest->requested_venue_id,
            ]);

            return $confirmation->fresh(['user', 'player.user', 'requestedVenue']);
        });
    }

    protected function assertMatchAllowsRescheduleRequests(GameMatch $match): void
    {
        if (
            $match->status === GameMatchStatus::VALIDATED
            || $match->status === GameMatchStatus::CANCELLED
            || $match->status === GameMatchStatus::POSTPONED
            || $match->status === GameMatchStatus::UNDER_REVIEW
        ) {
            throw new InvalidArgumentException('Este partido no admite solicitudes de reprogramación.');
        }
    }

    protected function resolvePlayerSide(GameMatch $match, Player $player): MatchResultReportSide
    {
        if ($this->entryContainsPlayer($match->homeEntry, $player)) {
            return MatchResultReportSide::HOME;
        }

        if ($this->entryContainsPlayer($match->awayEntry, $player)) {
            return MatchResultReportSide::AWAY;
        }

        throw new InvalidArgumentException('El jugador no participa en este partido.');
    }

    protected function entryContainsPlayer(?CategoryEntry $entry, Player $player): bool
    {
        if (!$entry) {
            return false;
        }

        if ($entry->entry_type === 'player') {
            return (int) $entry->player_id === (int) $player->id;
        }

        if ($entry->entry_type === 'team') {
            return $entry->team !== null
                && $entry->team->players->contains(
                    fn (Player $teamPlayer): bool => (int) $teamPlayer->id === (int) $player->id
                );
        }

        return false;
    }

    protected function assertScheduleIsAvailable(
        GameMatch $match,
        Carbon $requestedDateTime,
        int $venueId
    ): void {
        $championshipId = $match->round?->category?->championship_id;

        if (!$championshipId) {
            throw new InvalidArgumentException('No se ha podido determinar el campeonato del partido.');
        }

        $exists = GameMatch::query()
            ->whereKeyNot($match->id)
            ->where('venue_id', $venueId)
            ->where('scheduled_date', $requestedDateTime)
            ->whereHas('round.category', function ($query) use ($championshipId) {
                $query->where('championship_id', $championshipId);
            })
            ->exists();

        if ($exists) {
            throw new InvalidArgumentException('La pista seleccionada ya está ocupada en esa fecha y hora para otro partido del mismo campeonato.');
        }
    }
}
